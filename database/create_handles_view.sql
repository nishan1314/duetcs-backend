-- Create the 'handles' view by joining codeforces_handles and coder_handles
-- This view combines admin-managed handles with user-linked handles

-- Drop the view if it already exists
DROP VIEW IF EXISTS handles;

-- Create the view
-- coder_handles table: id, user_id, codeforces_handle, created_at, updated_at
-- codeforces_handles table: id, handle, name, created_at, updated_at
CREATE VIEW handles AS
SELECT 
    ch.user_id,
    ch.codeforces_handle AS handle,
    cfh.name AS admin_name,
    cfh.created_at AS admin_created_at,
    cfh.updated_at AS admin_updated_at,
    ch.created_at AS linked_at,
    ch.updated_at AS link_updated_at
FROM coder_handles ch
LEFT JOIN codeforces_handles cfh ON ch.codeforces_handle = cfh.handle;

-- Note: This view allows fetching user handle data along with any 
-- additional info (like name) stored in the admin codeforces_handles table
