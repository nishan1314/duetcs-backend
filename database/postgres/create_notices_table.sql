-- Notices Table for Admin Announcements
CREATE TABLE IF NOT EXISTS notices (
    id SERIAL PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    content TEXT,
    type VARCHAR(255) DEFAULT 'message',
    pdf_url VARCHAR(500),
    pdf_name VARCHAR(255),
    created_by INT NOT NULL,
    status VARCHAR(255) DEFAULT 'active',
    priority VARCHAR(255) DEFAULT 'medium',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ;
