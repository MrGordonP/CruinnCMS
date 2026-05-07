-- Remove duplicate folders created by obsolete 001_drivespace_core.sql
-- These were example folders that should not have been in migrations.
-- Keep only the first occurrence of each slug for existing databases.

DELETE f1 FROM folders f1
INNER JOIN folders f2
WHERE f1.slug = f2.slug
  AND f1.id > f2.id
  AND f1.slug IN ('council-documents', 'shared-resources', 'public-files');
