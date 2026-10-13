-- Removes the demo purchase requests, projects, bids and awards (PR-2026-D001..D020, SJ-BAC-2026-*-D01..D50).
DO $$
BEGIN
DELETE FROM contract_implementation_events WHERE contract_implementation_id IN (SELECT id FROM contract_implementations WHERE award_id IN (SELECT id FROM awards WHERE project_id IN (SELECT id FROM projects WHERE reference_no LIKE 'SJ-BAC-2026-%-D__')));
DELETE FROM contract_implementations WHERE award_id IN (SELECT id FROM awards WHERE project_id IN (SELECT id FROM projects WHERE reference_no LIKE 'SJ-BAC-2026-%-D__'));
DELETE FROM awards WHERE project_id IN (SELECT id FROM projects WHERE reference_no LIKE 'SJ-BAC-2026-%-D__');
DELETE FROM bids WHERE project_id IN (SELECT id FROM projects WHERE reference_no LIKE 'SJ-BAC-2026-%-D__');
DELETE FROM project_schedules WHERE project_id IN (SELECT id FROM projects WHERE reference_no LIKE 'SJ-BAC-2026-%-D__');
DELETE FROM project_requirements WHERE project_id IN (SELECT id FROM projects WHERE reference_no LIKE 'SJ-BAC-2026-%-D__');
DELETE FROM projects WHERE reference_no LIKE 'SJ-BAC-2026-%-D__';
DELETE FROM procurement_requests WHERE reference_no LIKE 'PR-2026-D%';
END $$;
