# Yasmin & Aliarose Dormitory Management System

This project has been restructured to follow standard secure directory architecture. 

## Directory Architecture:
- **/public:** Publicly accessible files (HTML, PHP frontend, JS, CSS, images, user uploads). Serve web traffic from this directory!
- **/private:** Restricted files, backups, logs, and sensitive materials. Not accessible from the web.
- **/config:** Configuration files. Contains `config.env` where database credentials and site-wide constants are stored securely.
- **/temp:** Temporary files requiring cleanup.
- **/scripts:** Automation and miscellaneous utility scripts.

## Database
The system automatically reads database configuration from `config/config.env`. Ensure your XAMPP MySQL has a database named `dormitory_db` and your root user password is configured in the `.env` file correctly. The database schema backup can be found in `private/backups/db.sql`.

## Accessing the Project
Since everything public corresponds to the `public/` directory, open your browser and navigate to:
**yadorm.vercel.app**

Enjoy the organized and bug-free system!
