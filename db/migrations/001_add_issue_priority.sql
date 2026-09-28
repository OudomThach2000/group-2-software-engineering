-- FR8: priority set by the supervisor during triage (UC3).  Owner: Oudom Thach.
-- NULL until the issue has been triaged. Run once on a database created from db/schema.sql:
--   USE community_issues;
--   SOURCE db/migrations/001_add_issue_priority.sql;
-- The same column should also be added to the issues table in db/schema.sql
-- (after photo_path) so a fresh install gets it without this step.

ALTER TABLE issues
  ADD COLUMN priority ENUM('Low','Medium','High','Urgent') NULL AFTER photo_path,
  ADD INDEX idx_issue_priority (priority);
