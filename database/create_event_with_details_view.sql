-- Create a view joining events and event_details on event_id
CREATE OR REPLACE VIEW event_with_details AS
SELECT 
    e.*, 
    ed.id AS detail_id,
    ed.detail_type,
    ed.content AS detail_content,
    ed.display_order AS detail_display_order,
    ed.created_at AS detail_created_at
FROM events e
LEFT JOIN event_details ed ON e.id = ed.event_id;