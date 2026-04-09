<div class="container">
    <div class="form-page">
        <h1><?= e($form['title']) ?></h1>

        <?php if (!empty($form['description'])): ?>
            <p class="form-description"><?= nl2br(e($form['description'])) ?></p>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
        <div class="flash flash-error" role="alert">
            <ul>
                <?php foreach ($errors as $err): ?>
                    <li><?= e($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <form method="post" action="/forms/<?= e($form['slug']) ?>" class="form-public" enctype="multipart/form-data">
            <?= csrf_field() ?>

            <?php foreach ($form['fields'] as $field): ?>
                <?php
                    $name = $field['name'];
                    $id = 'field-' . $name;
                    $required = !empty($field['validation']['required']);
                    $oldVal = $old[$name] ?? '';
                    $type = $field['field_type'];
                ?>

                <?php if ($type === 'heading'): ?>
                    <h2 class="form-field-heading"><?= e($field['label']) ?></h2>
                    <?php if ($field['help_text']): ?>
                        <p class="form-heading-desc"><?= e($field['help_text']) ?></p>
                    <?php endif; ?>
                    <?php continue; ?>
                <?php endif; ?>

                <?php if ($type === 'paragraph'): ?>
                    <p class="form-field-paragraph"><?= nl2br(e($field['label'])) ?></p>
                    <?php continue; ?>
                <?php endif; ?>

                <?php if ($type === 'hidden'): ?>
                    <input type="hidden" name="<?= e($name) ?>" value="<?= e($oldVal ?: ($field['placeholder'] ?? '')) ?>">
                    <?php continue; ?>
                <?php endif; ?>

                <div class="form-group">
                    <?php if ($type !== 'checkbox'): ?>
                        <label for="<?= e($id) ?>">
                            <?= e($field['label']) ?>
                            <?php if ($required): ?><span class="required">*</span><?php endif; ?>
                        </label>
                    <?php endif; ?>

                    <?php if ($type === 'text' || $type === 'email' || $type === 'number' || $type === 'date'): ?>
                        <input type="<?= e($type) ?>" id="<?= e($id) ?>" name="<?= e($name) ?>"
                               class="form-input"
                               value="<?= e($oldVal) ?>"
                               <?php if ($field['placeholder']): ?>placeholder="<?= e($field['placeholder']) ?>"<?php endif; ?>
                               <?php if ($required): ?>required<?php endif; ?>
                               <?php if (!empty($field['validation']['min_length'])): ?>minlength="<?= (int)$field['validation']['min_length'] ?>"<?php endif; ?>
                               <?php if (!empty($field['validation']['max_length'])): ?>maxlength="<?= (int)$field['validation']['max_length'] ?>"<?php endif; ?>
                               <?php if (isset($field['validation']['min'])): ?>min="<?= e($field['validation']['min']) ?>"<?php endif; ?>
                               <?php if (isset($field['validation']['max'])): ?>max="<?= e($field['validation']['max']) ?>"<?php endif; ?>>

                    <?php elseif ($type === 'textarea'): ?>
                        <textarea id="<?= e($id) ?>" name="<?= e($name) ?>"
                                  class="form-input" rows="4"
                                  <?php if ($field['placeholder']): ?>placeholder="<?= e($field['placeholder']) ?>"<?php endif; ?>
                                  <?php if ($required): ?>required<?php endif; ?>
                                  <?php if (!empty($field['validation']['max_length'])): ?>maxlength="<?= (int)$field['validation']['max_length'] ?>"<?php endif; ?>
                        ><?= e($oldVal) ?></textarea>

                    <?php elseif ($type === 'select'): ?>
                        <select id="<?= e($id) ?>" name="<?= e($name) ?>" class="form-input"
                                <?php if ($required): ?>required<?php endif; ?>>
                            <option value="">— Select —</option>
                            <?php foreach ($field['options'] ?? [] as $opt): ?>
                                <option value="<?= e($opt['value']) ?>"
                                        <?= $oldVal === $opt['value'] ? 'selected' : '' ?>>
                                    <?= e($opt['label']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                    <?php elseif ($type === 'radio'): ?>
                        <div class="radio-group" role="radiogroup">
                            <?php foreach ($field['options'] ?? [] as $opt): ?>
                                <label class="radio-label">
                                    <input type="radio" name="<?= e($name) ?>" value="<?= e($opt['value']) ?>"
                                           <?= $oldVal === $opt['value'] ? 'checked' : '' ?>
                                           <?php if ($required): ?>required<?php endif; ?>>
                                    <?= e($opt['label']) ?>
                                </label>
                            <?php endforeach; ?>
                        </div>

                    <?php elseif ($type === 'checkbox'): ?>
                        <label class="checkbox-label">
                            <input type="checkbox" name="<?= e($name) ?>" value="1"
                                   <?= !empty($oldVal) ? 'checked' : '' ?>
                                   <?php if ($required): ?>required<?php endif; ?>>
                            <?= e($field['label']) ?>
                            <?php if ($required): ?><span class="required">*</span><?php endif; ?>
                        </label>

                    <?php elseif ($type === 'checkbox_group'): ?>
                        <div class="checkbox-group">
                            <?php
                                $oldArr = is_array($oldVal) ? $oldVal : [];
                            ?>
                            <?php foreach ($field['options'] ?? [] as $opt): ?>
                                <label class="checkbox-label">
                                    <input type="checkbox" name="<?= e($name) ?>[]" value="<?= e($opt['value']) ?>"
                                           <?= in_array($opt['value'], $oldArr) ? 'checked' : '' ?>>
                                    <?= e($opt['label']) ?>
                                </label>
                            <?php endforeach; ?>
                        </div>

                    <?php elseif ($type === 'file'): ?>
                        <input type="file" id="<?= e($id) ?>" name="<?= e($name) ?>" class="form-input"
                               <?php if ($required): ?>required<?php endif; ?>>

                    <?php endif; ?>

                    <?php if ($field['help_text'] && $type !== 'checkbox'): ?>
                        <small class="form-help"><?= e($field['help_text']) ?></small>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary btn-block">Submit</button>
            </div>
        </form>
    </div>
</div>
