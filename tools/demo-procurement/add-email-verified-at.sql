-- Run once in the Neon SQL editor: the column that records when a bidder confirmed their email address.
ALTER TABLE users ADD COLUMN IF NOT EXISTS email_verified_at timestamp NULL;
