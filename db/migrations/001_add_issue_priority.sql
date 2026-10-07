-- FR8: priority set by the supervisor during triage (UC3).  Owner: Oudom Thach.
-- NULL until the issue has been triaged.
-- db/schema.sql now includes this column, so a fresh install does NOT need this file
-- (running it on such a database fails with "Duplicate column name 'priority'").
-- Run it once only on a database that was created from an older schema.sql:
--   USE community_issues;
--   SOURCE db/migrations/001_add_issue_priority.sql;

ALTER TABLE issues
  ADD COLUMN priority ENUM('Low','Medium','High','Urgent') NULL AFTER photo_path,
  ADD INDEX idx_issue_priority (priority);
