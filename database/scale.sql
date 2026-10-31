-- Indexes for the pages people load most. Each serves a specific query, so pages stay fast as tables grow.
ALTER TABLE collection
    ADD INDEX browse_all (showcase, bookID),
    ADD INDEX browse_city (showcase, city, bookID),
    ADD INDEX shelf (userID, showcase, bookID);
ALTER TABLE swap
    ADD INDEX owner_status (ownerID, status),
    ADD INDEX requester_status (requesterID, status),
    ADD INDEX reminders (status, kind, due_date);
ALTER TABLE messages ADD INDEX unread (swapID, read_at, senderID);
ALTER TABLE reports ADD INDEX target (target_type, target_id);
ALTER TABLE books ADD INDEX by_title (title(100));
ALTER TABLE books ADD FULLTEXT INDEX book_search (title, author);
ALTER TABLE users ADD FULLTEXT INDEX people_search (username, first_name, last_name);
