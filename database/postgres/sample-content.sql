-- Sample Content Data for DUETCS Dynamic Content Management System
-- Insert sample data for testing content management system
-- Run after admin schema has been imported

-- Sample Events
INSERT INTO events (title, description, event_date, event_time, venue, category, status, image_url, created_by) VALUES
('DUET IUPC 2025', 'Annual Inter University Programming Contest featuring teams from across the country competing in challenging programming problems.', '2025-05-09', '09:00:00', 'DUET Campus - Computer Labs', 'Competition', 'upcoming', '/img/events/DUET_IUPC_2025.jpg', 1),
('Tech Talk: AI & Machine Learning', 'Industry experts discuss the latest trends, applications, and career opportunities in AI and Machine Learning.', '2025-02-20', '14:00:00', 'DUET Auditorium', 'Workshop', 'upcoming', '/img/events/tech-talk-ai.jpg', 1),
('Web Development Bootcamp', 'Intensive bootcamp covering modern web development with React, Node.js, and MongoDB. Hands-on projects included.', '2025-03-01', '10:00:00', 'DUET Computer Lab', 'Workshop', 'upcoming', '/img/events/web-bootcamp.jpg', 1),
('Hackathon 2025', 'Build innovative projects in 24 hours. Open to all students. Prizes up to 50,000 BDT.', '2025-04-15', '08:00:00', 'DUET Campus', 'Hackathon', 'upcoming', '/img/events/hackathon-2025.jpg', 1),
('Database Design Fundamentals', 'Learn about database design principles, normalization, indexing and optimization techniques.', '2025-01-25', '15:00:00', 'DUET Computer Lab 2', 'Workshop', 'completed', '/img/events/database-design.jpg', 1);

-- Sample News Articles
INSERT INTO news (title, slug, description, content, image_url, category, author_id, status, published_at) VALUES
('DUETCS Announces Annual Awards', 'duetcs-announces-annual-awards', 'The society announces recipients of annual excellence awards in programming, leadership, and community service.', 'Exciting announcement! The DUETCS Annual Awards Ceremony will be held on January 20, 2025. This year, we are proud to recognize exceptional members who have contributed significantly to our community. Winners will receive certificates, cash prizes, and special recognition at the event.', '/img/news/annual-awards.jpg', 'Announcement', 1, 'published', '2025-01-15 10:00:00'),
('New Mentorship Program Launched', 'new-mentorship-program-launched', 'DUETCS launches comprehensive mentorship program pairing senior members with junior students.', 'We are thrilled to announce the launch of our new mentorship program! This initiative aims to guide junior members through their programming journey. Experienced mentors from the society will help with coding challenges, project guidance, and career development. Registration is now open for both mentors and mentees!', '/img/news/mentorship-program.jpg', 'Program', 1, 'published', '2025-01-12 14:30:00'),
('Coding Challenge Winners Announced', 'coding-challenge-winners-announced', 'Monthly coding challenge concludes with impressive solutions from talented members.', 'Congratulations to all participants in January\'s coding challenge! The challenge focused on algorithmic problem solving and optimization. We received over 50 submissions with creative solutions. The top 5 winners have been selected and will receive prizes next month.', '/img/news/coding-challenge.jpg', 'Achievement', 1, 'published', '2025-01-10 16:00:00'),
('Workshop on Cloud Computing Success', 'workshop-cloud-computing-success', 'Recent cloud computing workshop attracts over 200 participants from DUET and guest institutions.', 'Our recent workshop on Cloud Computing Architecture and Deployment was a great success! We had speakers from leading tech companies discussing AWS, Google Cloud, and Azure. Participants learned about scalability, security, and cost optimization. Thank you to all attendees!', '/img/news/cloud-workshop.jpg', 'Workshop', 1, 'published', '2025-01-08 11:00:00'),
('Society Ranked Top 5 in National Survey', 'society-ranked-top-5-national-survey', 'DUETCS earns recognition as one of the top 5 computer science societies in the country.', 'We are proud to announce that DUETCS has been ranked in the top 5 computer science societies in Bangladesh according to the National Student Survey. This achievement reflects our commitment to excellence, community engagement, and continuous innovation. Thank you to all our members!', '/img/news/national-recognition.jpg', 'Achievement', 1, 'published', '2025-01-05 09:00:00');

-- Sample Achievements
INSERT INTO achievements (title, description, achievement_date, category, image_url, display_order, is_featured, created_by) VALUES
('Won IUPC 2025 Finals', 'Our team secured first place in the DUET IUPC 2025 programming competition with outstanding performance.', '2025-05-10', 'Competition', '/img/achievements/iupc-2025.jpg', 1, 1, 1),
('Certified AWS Partners', 'Multiple members achieved AWS Solutions Architect certification, enhancing our cloud expertise.', '2025-01-20', 'Certification', '/img/achievements/aws-cert.jpg', 2, 1, 1),
('Published Research Papers', 'Three research papers by society members published in international conferences.', '2025-01-15', 'Research', '/img/achievements/research-papers.jpg', 3, 1, 1),
('100% Placement Rate', 'Members achieved 100% placement rate with positions at top tech companies.', '2024-12-31', 'Placement', '/img/achievements/placement.jpg', 4, 1, 1),
('Community Service Award', 'Recognized for outstanding contribution to tech education in underprivileged areas.', '2024-12-15', 'Service', '/img/achievements/community-service.jpg', 5, 0, 1);

-- Sample Executive Members
INSERT INTO executive_members (name, position, user_id, image_url, bio, email, linkedin, github, term_year, display_order, is_active) VALUES
('Md. Rafiul Islam', 'President', 2, '/img/executive/rafiul-islam.jpg', 'Passionate about competitive programming and mentoring.', 'rafiul@duet.edu.bd', 'linkedin.com/in/rafiulislam', 'github.com/rafiulislam', '2024-2025', 1, 1),
('Fatima Akter', 'Vice President', 3, '/img/executive/fatima-akter.jpg', 'Focused on community building and event management.', 'fatima@duet.edu.bd', 'linkedin.com/in/fatimaakter', 'github.com/fatimaakter', '2024-2025', 2, 1),
('Arif Hossain', 'General Secretary', 4, '/img/executive/arif-hossain.jpg', 'Expert in web development and system design.', 'arif@duet.edu.bd', 'linkedin.com/in/arifhossain', 'github.com/arifhossain', '2024-2025', 3, 1),
('Zahra Khan', 'Treasurer', 5, '/img/executive/zahra-khan.jpg', 'Experienced in finance management and budgeting.', 'zahra@duet.edu.bd', 'linkedin.com/in/zahrakhan', 'github.com/zahrakhan', '2024-2025', 4, 1),
('Rahman Ahmed', 'Joint Secretary', 6, '/img/executive/rahman-ahmed.jpg', 'Tech enthusiast and project coordinator.', 'rahman@duet.edu.bd', 'linkedin.com/in/rahmanahmed', 'github.com/rahmanahmed', '2024-2025', 5, 1);

-- Sample Wings (Divisions)
INSERT INTO wings (wing_name, wing_description, icon, color, image_url, is_active, display_order) VALUES
('Web Development Wing', 'Focuses on frontend and backend web technologies including React, Node.js, and modern frameworks.', 'Code', '#3498db', '/img/wings/web-dev.jpg', 1, 1),
('AI & Machine Learning Wing', 'Dedicated to artificial intelligence, machine learning, deep learning, and data science projects.', 'Brain', '#e74c3c', '/img/wings/ai-ml.jpg', 1, 2),
('Competitive Programming Wing', 'Focuses on algorithm design, data structures, and competitive programming contest preparation.', 'Trophy', '#f39c12', '/img/wings/competitive-prog.jpg', 1, 3),
('Mobile Development Wing', 'Specializes in iOS and Android app development using native and cross-platform frameworks.', 'Smartphone', '#9b59b6', '/img/wings/mobile-dev.jpg', 1, 4),
('DevOps & Cloud Wing', 'Covers cloud infrastructure, containerization, CI/CD pipelines, and deployment strategies.', 'Cloud', '#1abc9c', '/img/wings/devops.jpg', 1, 5);

-- Sample Gallery Images
INSERT INTO gallery_images (title, description, image_url, category, event_id, uploaded_by, display_order, is_featured) VALUES
('IUPC Opening Ceremony', 'Opening ceremony of DUET IUPC 2024 with teams from across the country.', '/img/gallery/iupc-opening.jpg', 'Competition', 1, 1, 1, 1),
('Participants Competing', 'Teams intensely working on programming problems during the competition.', '/img/gallery/iupc-competition.jpg', 'Competition', 1, 1, 2, 1),
('Award Distribution', 'Winners receiving trophies and certificates at the award ceremony.', '/img/gallery/iupc-awards.jpg', 'Competition', 1, 1, 3, 0),
('Tech Talk Session', 'Industry expert presenting on latest trends in software development.', '/img/gallery/tech-talk.jpg', 'Workshop', 2, 1, 1, 1),
('Networking Event', 'Members networking with speakers and fellow participants.', '/img/gallery/networking.jpg', 'Workshop', 2, 1, 2, 0),
('Group Photo', 'Group photo of all attendees and organizers.', '/img/gallery/group-photo.jpg', 'Event', NULL, 1, 3, 0);

-- Sample Website Content
INSERT INTO website_content (section_name, content_data, last_updated_by) VALUES
(
  'hero',
  '{
    "title": "Welcome to DUETCS",
    "subtitle": "DUET Computer Science Society",
    "description": "A vibrant community of computer science enthusiasts dedicated to excellence in programming, innovation, and knowledge sharing.",
    "image_url": "/img/hero-bg.jpg",
    "cta_text": "Join Us Now",
    "cta_link": "/join",
    "highlight": "Join 500+ members in our community"
  }',
  1
),
(
  'about',
  '{
    "title": "About DUETCS",
    "intro": "Founded in 2015, DUET Computer Science Society has grown into one of the leading tech communities in Bangladesh.",
    "mission": "To foster excellence in computer science education and promote innovation through collaborative learning and hands-on projects.",
    "vision": "To become a global leader in promoting computer science talent and innovation.",
    "core_values": ["Excellence", "Innovation", "Collaboration", "Integrity", "Community"],
    "stats": {
      "members": "500+",
      "events_yearly": "30+",
      "projects": "50+",
      "founded": "2015"
    }
  }',
  1
),
(
  'features',
  '{
    "title": "What We Offer",
    "features": [
      {
        "icon": "Users",
        "title": "Community",
        "description": "Join a vibrant community of passionate developers and learners"
      },
      {
        "icon": "BookOpen",
        "title": "Learning",
        "description": "Access workshops, mentorship, and continuous learning opportunities"
      },
      {
        "icon": "Zap",
        "title": "Innovation",
        "description": "Collaborate on exciting projects and build innovative solutions"
      },
      {
        "icon": "Award",
        "title": "Recognition",
        "description": "Get recognized for your contributions and achievements"
      }
    ]
  }',
  1
),
(
  'legacy',
  '{
    "title": "Our Journey",
    "description": "From humble beginnings to becoming a leading tech society, our journey is filled with milestones and achievements.",
    "timeline": [
      {
        "year": "2015",
        "title": "Society Founded",
        "description": "DUETCS was established with vision to create excellence in computer science"
      },
      {
        "year": "2017",
        "title": "First IUPC",
        "description": "Organized first DUET IUPC, now an annual flagship event"
      },
      {
        "year": "2019",
        "title": "500 Members",
        "description": "Reached milestone of 500 active members"
      },
      {
        "year": "2023",
        "title": "International Recognition",
        "description": "Recognized as top computer science society in South Asia"
      },
      {
        "year": "2025",
        "title": "Digital Transformation",
        "description": "Launched comprehensive digital platform for members"
      }
    ]
  }',
  1
);

-- Commit the changes
COMMIT;

-- Verification queries
SELECT 'Events Count:' as info, COUNT(*) as count FROM events;
SELECT 'News Count:' as info, COUNT(*) as count FROM news;
SELECT 'Achievements Count:' as info, COUNT(*) as count FROM achievements;
SELECT 'Executive Members Count:' as info, COUNT(*) as count FROM executive_members;
SELECT 'Wings Count:' as info, COUNT(*) as count FROM wings;
SELECT 'Gallery Images Count:' as info, COUNT(*) as count FROM gallery_images;
SELECT 'Website Content Sections:' as info, COUNT(*) as count FROM website_content;
