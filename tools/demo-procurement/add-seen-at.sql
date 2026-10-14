-- Run once in the Neon Query editor if the deploy did not run the migration by itself:
-- adds the column behind the NEW mark of Purchase requests, and counts every request that exists now as seen.
DO $$
BEGIN
ALTER TABLE procurement_requests ADD COLUMN IF NOT EXISTS seen_at timestamp NULL;
UPDATE procurement_requests SET seen_at = now() WHERE seen_at IS NULL;
END $$;

-- Same for projects (the NEW mark in the Projects list).
DO $$
BEGIN
ALTER TABLE projects ADD COLUMN IF NOT EXISTS seen_at timestamp NULL;
UPDATE projects SET seen_at = now() WHERE seen_at IS NULL;
END $$;
