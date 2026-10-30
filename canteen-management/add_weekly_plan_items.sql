-- Run this once in phpMyAdmin (or your MySQL console) on each teammate's local DB,
-- same as add_item_favorites.sql - it is not tracked via Git/auto-applied.

CREATE TABLE weekly_plan_items (
    plan_item_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    plan_date DATE NOT NULL,
    item_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES menu_items(item_id) ON DELETE CASCADE
);