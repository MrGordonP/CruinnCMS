<?php

declare(strict_types=1);

namespace Cruinn\Module\Mailbox\Services;

/**
 * ImapSocket — pure-PHP IMAP client using stream_socket_client().
 *
 * Replaces php-imap extension calls entirely. Implements only the subset of
 * RFC 3501 needed by MailboxService.
 *
 * Body-type constants mirror the php-imap extension values so calling code
 * can use them without change.
 */
class ImapSocket
{
    // Body type constants (RFC 3501 §6.4.5)
    public const TYPETEXT      = 0;
    public const TYPEMULTIPART = 1;
    public const TYPEMESSAGE   = 2;
    public const TYPEAPPLICATION = 3;
    public const TYPEAUDIO     = 4;
    public const TYPEIMAGE     = 5;
    public const TYPEVIDEO     = 6;
    public const TYPEOTHER     = 7;

    // Encoding constants (RFC 2045)
    public const ENC7BIT             = 0;
    public const ENC8BIT             = 1;
    public const ENCBINARY           = 2;
    public const ENCBASE64           = 3;
    public const ENCQUOTEDPRINTABLE  = 4;
    public const ENCOTHER            = 5;

    private mixed  $socket = null;
    private int    $tagSeq = 0;
    private string $selectedFolder = '';

    /**
     * Open an IMAP connection and authenticate.
     *
     * @param string $enc  'ssl' | 'tls' | 'none'
     * @throws \RuntimeException
     */
    public function connect(string $host, int $port, string $enc, string $user, string $password): void
    {
        $enc = strtolower($enc);

        $target  = ($enc === 'ssl') ? "ssl://{$host}:{$port}" : "tcp://{$host}:{$port}";
        $context = stream_context_create([
            'ssl' => [
                'verify_peer'       => true,
                'verify_peer_name'  => true,
                'SNI_enabled'       => true,
                'peer_name'         => $host,
            ],
        ]);

        $errno  = 0;
        $errstr = '';
        $sock   = @stream_socket_client($target, $errno, $errstr, 30, STREAM_CLIENT_CONNECT, $context);

        if ($sock === false) {
            throw new \RuntimeException("IMAP connect to {$host}:{$port} failed: {$errstr} ({$errno})");
        }

        stream_set_timeout($sock, 30);
        $this->socket = $sock;

        // Read server greeting
        $greeting = $this->readLine();
        if (!str_starts_with($greeting, '* OK')) {
            throw new \RuntimeException("Unexpected IMAP greeting: {$greeting}");
        }

        // STARTTLS upgrade for plain connections requesting TLS
        if ($enc === 'tls') {
            $this->sendCommand('STARTTLS');
            if (!stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new \RuntimeException('STARTTLS negotiation failed');
            }
        }

        // LOGIN
        $resp = $this->sendCommand('LOGIN ' . $this->quoteString($user) . ' ' . $this->quoteString($password));
        if (!$this->isOk($resp)) {
            throw new \RuntimeException('IMAP LOGIN failed: ' . implode(' ', $resp));
        }
    }

    public function disconnect(): void
    {
        if ($this->socket !== null) {
            try { $this->sendCommand('LOGOUT'); } catch (\Throwable) {}
            fclose($this->socket);
            $this->socket = null;
        }
        $this->selectedFolder = '';
        $this->tagSeq = 0;
    }

    // -------------------------------------------------------------------------
    // Folder operations
    // -------------------------------------------------------------------------

    /**
     * Return flat list of folder names.
     *
     * @return string[]
     */
    public function listFolders(): array
    {
        $lines = $this->sendCommand('LIST "" "*"');
        $folders = [];
        foreach ($lines as $line) {
            if (!str_starts_with($line, '* LIST')) {
                continue;
            }
            // * LIST (\HasNoChildren) "/" "INBOX"
            // * LIST (\HasNoChildren) "/" INBOX.Sent
            if (preg_match('/\* LIST \([^)]*\) "?[^"]+"? (.+)$/', $line, $m)) {
                $name = trim($m[1]);
                // Strip surrounding quotes
                if ($name[0] === '"') {
                    $name = stripslashes(substr($name, 1, -1));
                }
                $folders[] = $name;
            }
        }
        return $folders;
    }

    /**
     * SELECT a folder. Cached — only issues SELECT when folder changes.
     */
    public function selectFolder(string $folder): void
    {
        if ($this->selectedFolder === $folder) {
            return;
        }
        $resp = $this->sendCommand('SELECT ' . $this->quoteString($folder));
        if (!$this->isOk($resp)) {
            throw new \RuntimeException('IMAP SELECT failed for ' . $folder . ': ' . implode(' | ', $resp));
        }
        $this->selectedFolder = $folder;
    }

    // -------------------------------------------------------------------------
    // UID operations
    // -------------------------------------------------------------------------

    /**
     * UID SEARCH — returns int[] of matching UIDs.
     *
     * @return int[]
     */
    public function uidSearch(string $folder, string $criteria): array
    {
        $this->selectFolder($folder);
        $lines = $this->sendCommand('UID SEARCH ' . $criteria);
        foreach ($lines as $line) {
            if (str_starts_with($line, '* SEARCH')) {
                $parts = explode(' ', trim($line));
                array_shift($parts); // *
                array_shift($parts); // SEARCH
                return array_map('intval', array_filter($parts, 'is_numeric'));
            }
        }
        return [];
    }

    /**
     * Fetch envelope + flags for a single UID.
     * Returns stdClass with properties matching what MailboxService needs from imap_headerinfo:
     *   subject, from[], fromaddress, toaddress, ccaddress,
     *   message_id, in_reply_to, udate, Unseen, Answered, Flagged
     */
    public function uidFetchEnvelope(string $folder, int $uid): ?object
    {
        $this->selectFolder($folder);
        $lines = $this->sendCommand('UID FETCH ' . $uid . ' (FLAGS ENVELOPE INTERNALDATE)');

        $raw = implode("\r\n", $lines);
        $fetchBlock = $this->extractFetchBlock($raw, $uid);
        if ($fetchBlock === null) {
            return null;
        }

        $flags    = $this->parseFetchFlags($fetchBlock);
        $envelope = $this->parseFetchEnvelope($fetchBlock);
        $date     = $this->parseFetchInternalDate($fetchBlock);

        if ($envelope === null) {
            return null;
        }

        $obj = new \stdClass();
        $obj->subject     = $envelope[1] ?? '';
        $obj->message_id  = trim($envelope[9] ?? '');
        $obj->in_reply_to = trim($envelope[8] ?? '');
        $obj->udate       = $date;
        $obj->Unseen      = !in_array('\\Seen', $flags, true);
        $obj->Answered    = in_array('\\Answered', $flags, true);
        $obj->Flagged     = in_array('\\Flagged', $flags, true);

        // from
        $fromList = $this->parseAddressList($envelope[2] ?? 'NIL');
        $obj->from        = $fromList;
        $obj->fromaddress = $this->addressListToString($fromList);

        // to
        $toList = $this->parseAddressList($envelope[5] ?? 'NIL');
        $obj->toaddress = $this->addressListToString($toList);

        // cc
        $ccList = $this->parseAddressList($envelope[6] ?? 'NIL');
        $obj->ccaddress = $this->addressListToString($ccList);

        return $obj;
    }

    /**
     * Fetch BODYSTRUCTURE for a single UID.
     * Returns stdClass tree matching imap_fetchstructure shape.
     */
    public function uidFetchStructure(string $folder, int $uid): ?object
    {
        $this->selectFolder($folder);
        $lines = $this->sendCommand('UID FETCH ' . $uid . ' (BODYSTRUCTURE)');

        $raw = implode("\r\n", $lines);
        $fetchBlock = $this->extractFetchBlock($raw, $uid);
        if ($fetchBlock === null) {
            return null;
        }

        if (!preg_match('/BODYSTRUCTURE\s+(\(.*)/si', $fetchBlock, $m)) {
            return null;
        }

        $structureText = $m[1];
        // Trim trailing ) from fetch close
        $paren = $this->extractBalancedParens($structureText, 0);
        return $this->parseBodyStructure($paren);
    }

    /**
     * Fetch a body part by section number (e.g. "1", "1.2", "TEXT").
     */
    public function uidFetchBodyPart(string $folder, int $uid, string $partNum): string
    {
        $this->selectFolder($folder);
        $section = ($partNum === '' || $partNum === '1') ? 'BODY[1]' : 'BODY[' . $partNum . ']';
        $lines   = $this->sendCommand('UID FETCH ' . $uid . ' (' . $section . ')');

        $collecting = false;
        $buffer     = '';
        $byteCount  = -1;

        foreach ($lines as $line) {
            if (!$collecting) {
                // Look for: * N FETCH (BODY[x] {byteCount}
                if (preg_match('/\{(\d+)\}\s*$/', $line, $m)) {
                    $byteCount  = (int) $m[1];
                    $collecting = true;
                    continue;
                }
                // Inline literal: BODY[1] "data"
                if (preg_match('/BODY\[' . preg_quote($partNum, '/') . '\] "(.*)"/i', $line, $m)) {
                    $buffer = $m[1];
                }
            } else {
                // Accumulate until we have byteCount bytes
                if ($byteCount >= 0 && strlen($buffer) < $byteCount) {
                    $buffer .= $line . "\r\n";
                }
                if (strlen($buffer) >= $byteCount) {
                    break;
                }
            }
        }

        return $buffer;
    }

    /**
     * UID COPY message to another folder.
     */
    public function uidCopy(string $folder, int $uid, string $toFolder): void
    {
        $this->selectFolder($folder);
        $resp = $this->sendCommand('UID COPY ' . $uid . ' ' . $this->quoteString($toFolder));
        if (!$this->isOk($resp)) {
            throw new \RuntimeException('UID COPY failed: ' . implode(' ', $resp));
        }
    }

    /**
     * UID STORE — add or remove flags.
     * $flags example: '\\Deleted' or '\\Seen \\Flagged'
     */
    public function uidStore(string $folder, int $uid, string $flags, bool $add = true): void
    {
        $this->selectFolder($folder);
        $op   = $add ? '+FLAGS' : '-FLAGS';
        $resp = $this->sendCommand('UID STORE ' . $uid . ' ' . $op . ' (' . $flags . ')');
        if (!$this->isOk($resp)) {
            throw new \RuntimeException('UID STORE failed: ' . implode(' ', $resp));
        }
    }

    /**
     * EXPUNGE the selected folder.
     */
    public function expunge(string $folder): void
    {
        $this->selectFolder($folder);
        $this->sendCommand('EXPUNGE');
    }

    // -------------------------------------------------------------------------
    // Low-level I/O
    // -------------------------------------------------------------------------

    /**
     * Send an IMAP command, read all response lines, return them.
     *
     * @return string[]
     * @throws \RuntimeException
     */
    private function sendCommand(string $command): array
    {
        $tag  = 'A' . str_pad((string) (++$this->tagSeq), 5, '0', STR_PAD_LEFT);
        $line = $tag . ' ' . $command . "\r\n";

        if (fwrite($this->socket, $line) === false) {
            throw new \RuntimeException('IMAP write failed');
        }

        $lines = [];
        while (true) {
            $response = $this->readLine();
            $lines[]  = $response;

            // Tagged response ends the exchange
            if (str_starts_with($response, $tag . ' ')) {
                break;
            }

            // Handle literal continuation {n}
            if (preg_match('/\{(\d+)\}$/', $response, $m)) {
                $byteCount = (int) $m[1];
                $data      = '';
                while (strlen($data) < $byteCount) {
                    $chunk = fread($this->socket, $byteCount - strlen($data));
                    if ($chunk === false || $chunk === '') {
                        break;
                    }
                    $data .= $chunk;
                }
                $lines[] = $data;
            }
        }

        return $lines;
    }

    private function readLine(): string
    {
        $line = fgets($this->socket, 8192);
        if ($line === false) {
            throw new \RuntimeException('IMAP read failed (connection closed?)');
        }
        return rtrim($line, "\r\n");
    }

    /** Check if the final tagged response is OK */
    private function isOk(array $lines): bool
    {
        $last = end($lines);
        return (bool) preg_match('/^A\d+ OK/i', $last);
    }

    private function quoteString(string $s): string
    {
        // If it contains special chars, use quoted string; otherwise bare atom
        if (preg_match('/[\x00-\x1f\x7f "\\\\]/', $s)) {
            return '"' . addcslashes($s, '"\\') . '"';
        }
        return '"' . $s . '"';
    }

    // -------------------------------------------------------------------------
    // FETCH response parsers
    // -------------------------------------------------------------------------

    /**
     * Extract the content of a FETCH response for a given UID.
     * Returns the inner content string between the outer parens, or null.
     */
    private function extractFetchBlock(string $raw, int $uid): ?string
    {
        // Match: * N FETCH (...)  where N is any sequence number
        // The UID appears inside as UID <n>
        if (!preg_match('/\* \d+ FETCH \((.+)/s', $raw, $m)) {
            return null;
        }
        $block = $m[1];
        // Verify UID matches
        if (!preg_match('/\bUID\s+' . $uid . '\b/', $block)) {
            return null;
        }
        return $block;
    }

    /** Parse FLAGS from a FETCH block: (\Seen \Answered) → ['\Seen', '\Answered'] */
    private function parseFetchFlags(string $block): array
    {
        if (!preg_match('/FLAGS\s+\(([^)]*)\)/', $block, $m)) {
            return [];
        }
        $parts = preg_split('/\s+/', trim($m[1]));
        return array_filter($parts);
    }

    /** Parse ENVELOPE from a FETCH block. Returns array of 10 envelope fields. */
    private function parseFetchEnvelope(string $block): ?array
    {
        if (!preg_match('/ENVELOPE\s+(\(.*)/s', $block, $m)) {
            return null;
        }
        $envText  = $m[1];
        $balanced = $this->extractBalancedParens($envText, 0);
        return $this->parseEnvelopeFields($balanced);
    }

    /** Parse INTERNALDATE from FETCH block → Unix timestamp */
    private function parseFetchInternalDate(string $block): int
    {
        if (!preg_match('/INTERNALDATE\s+"([^"]+)"/', $block, $m)) {
            return time();
        }
        $ts = strtotime($m[1]);
        return $ts !== false ? $ts : time();
    }

    // -------------------------------------------------------------------------
    // ENVELOPE parser
    // -------------------------------------------------------------------------

    /**
     * Parse IMAP ENVELOPE list into 10-element array:
     * [0] date, [1] subject, [2] from, [3] sender, [4] reply-to,
     * [5] to, [6] cc, [7] bcc, [8] in-reply-to, [9] message-id
     */
    private function parseEnvelopeFields(string $envelope): array
    {
        // Remove outer parens
        $inner = trim(substr($envelope, 1, -1));
        $fields = [];
        $pos    = 0;
        $len    = strlen($inner);

        while ($pos < $len && count($fields) < 10) {
            while ($pos < $len && $inner[$pos] === ' ') {
                $pos++;
            }
            if ($pos >= $len) {
                break;
            }

            if ($inner[$pos] === '(') {
                // Nested list (address list)
                $sub = $this->extractBalancedParens($inner, $pos);
                $fields[] = $sub;
                $pos += strlen($sub);
            } elseif (substr($inner, $pos, 3) === 'NIL') {
                $fields[] = 'NIL';
                $pos += 3;
            } elseif ($inner[$pos] === '"') {
                // Quoted string
                $end = $pos + 1;
                while ($end < $len) {
                    if ($inner[$end] === '\\') { $end += 2; continue; }
                    if ($inner[$end] === '"')  { $end++; break; }
                    $end++;
                }
                $fields[] = stripslashes(substr($inner, $pos + 1, $end - $pos - 2));
                $pos = $end;
            } elseif (preg_match('/^\{(\d+)\}/', substr($inner, $pos), $m)) {
                // Literal
                $literalLen = (int) $m[1];
                $pos += strlen($m[0]) + 2; // skip {n}\r\n
                $fields[] = substr($inner, $pos, $literalLen);
                $pos += $literalLen;
            } else {
                // Atom
                $end = $pos;
                while ($end < $len && $inner[$end] !== ' ' && $inner[$end] !== ')') {
                    $end++;
                }
                $fields[] = substr($inner, $pos, $end - $pos);
                $pos = $end;
            }
        }

        return $fields;
    }

    /**
     * Parse an IMAP address list string like ((name NIL mailbox host) ...)
     * into array of stdClass {name, mailbox, host, personal, ...}
     */
    private function parseAddressList(string $addrList): array
    {
        if (trim($addrList) === 'NIL' || $addrList === '') {
            return [];
        }

        $result = [];
        // The list looks like: ((f1 f2 f3 f4) (f1 f2 f3 f4) ...)
        $inner = trim(substr($addrList, 1, -1));
        $pos   = 0;
        $len   = strlen($inner);

        while ($pos < $len) {
            while ($pos < $len && $inner[$pos] === ' ') { $pos++; }
            if ($pos >= $len) { break; }
            if ($inner[$pos] !== '(') { $pos++; continue; }

            $sub = $this->extractBalancedParens($inner, $pos);
            $pos += strlen($sub);

            // Each address: (personal_name NIL mailbox host)
            $fields = $this->parseEnvelopeFields($sub); // reuse same quoted-string parser
            $addr   = new \stdClass();
            $addr->personal = isset($fields[0]) && $fields[0] !== 'NIL' ? $this->mimeDecodeHeader($fields[0]) : '';
            $addr->mailbox  = isset($fields[2]) && $fields[2] !== 'NIL' ? $fields[2] : '';
            $addr->host     = isset($fields[3]) && $fields[3] !== 'NIL' ? $fields[3] : '';
            $result[] = $addr;
        }

        return $result;
    }

    /**
     * Render an address list to a display string like "Name <user@host>, ..."
     */
    private function addressListToString(array $list): string
    {
        $parts = [];
        foreach ($list as $addr) {
            $email = $addr->mailbox . '@' . $addr->host;
            $parts[] = $addr->personal ? $addr->personal . ' <' . $email . '>' : $email;
        }
        return implode(', ', $parts);
    }

    // -------------------------------------------------------------------------
    // BODYSTRUCTURE parser
    // -------------------------------------------------------------------------

    /**
     * Parse a BODYSTRUCTURE parenthesised string into a stdClass tree.
     * Produces the same shape as imap_fetchstructure().
     */
    private function parseBodyStructure(string $text, int $depth = 0): object
    {
        $obj   = new \stdClass();
        $inner = trim(substr($text, 1, -1));

        if ($inner === '') {
            $obj->type    = self::TYPEOTHER;
            $obj->subtype = '';
            $obj->parts   = [];
            return $obj;
        }

        // Determine if multipart: starts with another '('
        if ($inner[0] === '(') {
            $obj->type    = self::TYPEMULTIPART;
            $obj->parts   = [];
            $obj->subtype = '';
            $pos = 0;
            $len = strlen($inner);

            while ($pos < $len && $inner[$pos] === '(') {
                $sub = $this->extractBalancedParens($inner, $pos);
                $obj->parts[] = $this->parseBodyStructure($sub, $depth + 1);
                $pos += strlen($sub);
                while ($pos < $len && $inner[$pos] === ' ') { $pos++; }
            }

            // Remaining: subtype and extension data
            if ($pos < $len) {
                $remaining = substr($inner, $pos);
                $fields    = $this->parseStructureFields($remaining);
                $obj->subtype = strtoupper($fields[0] ?? '');
            }
            return $obj;
        }

        // Non-multipart: type subtype body-fields ...
        $fields = $this->parseStructureFields($inner);

        $typeStr  = strtoupper($fields[0] ?? 'TEXT');
        $subtype  = strtoupper($fields[1] ?? '');

        $obj->type    = $this->bodyTypeCode($typeStr);
        $obj->subtype = $subtype;

        // Parameters: ("name" "value" ...) or NIL
        $obj->parameters  = $this->parseParamList($fields[2] ?? 'NIL');
        $obj->id          = ($fields[3] ?? 'NIL') === 'NIL' ? '' : $fields[3];
        $obj->description = ($fields[4] ?? 'NIL') === 'NIL' ? '' : $fields[4];
        $obj->encoding    = $this->encodingCode($fields[5] ?? '7BIT');
        $obj->bytes       = isset($fields[6]) && is_numeric($fields[6]) ? (int) $fields[6] : 0;

        // Disposition + dparameters (extension data)
        // Field [8] for non-message bodies is disposition or NIL
        $dispField = $fields[8] ?? 'NIL';
        $obj->disposition  = '';
        $obj->dparameters  = [];

        if ($dispField !== 'NIL' && $dispField !== '') {
            if ($dispField[0] === '(') {
                $dispFields = $this->parseStructureFields(substr($dispField, 1, -1));
                $obj->disposition = strtolower($dispFields[0] ?? '');
                $obj->dparameters = $this->parseParamList($dispFields[1] ?? 'NIL');
            } elseif (is_string($dispField)) {
                $obj->disposition = strtolower($dispField);
            }
        }

        return $obj;
    }

    /**
     * Parse a sequence of IMAP structure fields (atoms, quoted strings, NIL, nested lists).
     * Returns flat array of values (nested lists returned as-is parenthesised strings).
     */
    private function parseStructureFields(string $input): array
    {
        $fields = [];
        $pos    = 0;
        $len    = strlen($input);

        while ($pos < $len) {
            while ($pos < $len && $input[$pos] === ' ') { $pos++; }
            if ($pos >= $len) { break; }

            if ($input[$pos] === '(') {
                $sub = $this->extractBalancedParens($input, $pos);
                $fields[] = $sub;
                $pos += strlen($sub);
            } elseif (substr($input, $pos, 3) === 'NIL') {
                $fields[] = 'NIL';
                $pos += 3;
            } elseif ($input[$pos] === '"') {
                $end = $pos + 1;
                while ($end < $len) {
                    if ($input[$end] === '\\') { $end += 2; continue; }
                    if ($input[$end] === '"')  { $end++; break; }
                    $end++;
                }
                $fields[] = stripslashes(substr($input, $pos + 1, $end - $pos - 2));
                $pos = $end;
            } else {
                // Atom / number
                $end = $pos;
                while ($end < $len && $input[$end] !== ' ' && $input[$end] !== ')') { $end++; }
                $fields[] = substr($input, $pos, $end - $pos);
                $pos = $end;
            }
        }

        return $fields;
    }

    /**
     * Parse a BODYSTRUCTURE parameter list like ("name" "value" "name2" "value2")
     * into array of stdClass {attribute, value}.
     */
    private function parseParamList(string $paramText): array
    {
        if ($paramText === 'NIL' || $paramText === '') {
            return [];
        }
        $inner  = substr($paramText, 1, -1);
        $fields = $this->parseStructureFields($inner);
        $result = [];
        for ($i = 0; $i + 1 < count($fields); $i += 2) {
            $p            = new \stdClass();
            $p->attribute = strtolower($fields[$i]);
            $p->value     = $fields[$i + 1];
            $result[]     = $p;
        }
        return $result;
    }

    /** Extract a balanced parenthesised substring starting at $offset */
    private function extractBalancedParens(string $s, int $offset): string
    {
        $depth = 0;
        $len   = strlen($s);
        $start = $offset;
        $inQuote = false;

        for ($i = $offset; $i < $len; $i++) {
            if ($inQuote) {
                if ($s[$i] === '\\') { $i++; continue; }
                if ($s[$i] === '"')  { $inQuote = false; }
                continue;
            }
            if ($s[$i] === '"') { $inQuote = true; continue; }
            if ($s[$i] === '(') { $depth++; }
            if ($s[$i] === ')') {
                $depth--;
                if ($depth === 0) {
                    return substr($s, $start, $i - $start + 1);
                }
            }
        }

        return substr($s, $start);
    }

    private function bodyTypeCode(string $type): int
    {
        return match ($type) {
            'TEXT'        => self::TYPETEXT,
            'MULTIPART'   => self::TYPEMULTIPART,
            'MESSAGE'     => self::TYPEMESSAGE,
            'APPLICATION' => self::TYPEAPPLICATION,
            'AUDIO'       => self::TYPEAUDIO,
            'IMAGE'       => self::TYPEIMAGE,
            'VIDEO'       => self::TYPEVIDEO,
            default       => self::TYPEOTHER,
        };
    }

    private function encodingCode(string $enc): int
    {
        return match (strtoupper($enc)) {
            '7BIT'             => self::ENC7BIT,
            '8BIT'             => self::ENC8BIT,
            'BINARY'           => self::ENCBINARY,
            'BASE64'           => self::ENCBASE64,
            'QUOTED-PRINTABLE' => self::ENCQUOTEDPRINTABLE,
            default            => self::ENCOTHER,
        };
    }

    // -------------------------------------------------------------------------
    // Header decode helper
    // -------------------------------------------------------------------------

    /**
     * Decode MIME-encoded header value (replaces imap_utf8()).
     * Uses mb_decode_mimeheader (always available in PHP 8+).
     */
    public function mimeDecodeHeader(string $value): string
    {
        // mb_decode_mimeheader handles =?charset?B/Q?...?= sequences
        return mb_decode_mimeheader($value);
    }
}
