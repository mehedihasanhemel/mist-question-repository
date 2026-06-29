# MIST Question Repository

A web-based question bank for MIST (Military Institute of Science and Technology) that allows faculty to upload, organize, and share question papers with role-based access control.

## Features

- **Role-based access** — Viewer, Submitter, Resource Manager, Admin (users can hold multiple roles)
- **Hierarchical folders** — Nested folder structure with per-user folder access and optional sub-folder inheritance
- **File uploads** — Upload question papers (PDF/images) with title, year, and metadata
- **Inline PDF viewer** — View PDFs directly in the browser via PDF.js
- **Microsoft OAuth2 login** — Sign in with MIST Microsoft/Azure AD accounts
- **Admin panel** — Manage users, folders, and files; assign roles and folder permissions
- **Password reset** — Email-based forgot password flow

## Requirements

- PHP 8.1+
- MySQL 8+ or MariaDB 10.6+
- Apache 2.4 with `mod_rewrite` enabled
- PHP extensions: `pdo_mysql`, `curl`, `mbstring`, `xml`

## Installation

**1. Clone the repository**

```bash
git clone https://github.com/mehedihasanhemel/mist-question-repository.git qrepo
```

**2. Configure the database**

Create a MySQL database and user:

```sql
CREATE DATABASE qrepo_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'qrepo_user'@'localhost' IDENTIFIED BY 'your_password';
GRANT ALL PRIVILEGES ON qrepo_db.* TO 'qrepo_user'@'localhost';
```

Then create the tables (see [Database Schema](#database-schema) below).

**3. Configure the application**

```bash
cp includes/config.example.php includes/config.php
```

Edit `includes/config.php` with your database credentials and Microsoft OAuth details.

**4. Set upload permissions**

```bash
mkdir -p uploads
chmod 775 uploads
chown www-data:www-data uploads
```

**5. Configure Apache**

Add an alias in your Apache vhost:

```apache
Alias /qrepo /var/www/qrepo
<Directory /var/www/qrepo>
    Options -Indexes +FollowSymLinks
    AllowOverride All
    Require all granted
</Directory>
```

The app runs at `http://your-domain/qrepo/`.

## Database Schema

```sql
CREATE TABLE qrepo_admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE qrepo_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) DEFAULT NULL,
    role ENUM('viewer','submitter','resource_manager','admin') DEFAULT 'viewer',
    roles VARCHAR(500) DEFAULT NULL,
    auth_provider ENUM('local','microsoft','both') DEFAULT 'local',
    status ENUM('active','inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE qrepo_folders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    parent_id INT DEFAULT NULL,
    sort_order INT DEFAULT 0,
    FOREIGN KEY (parent_id) REFERENCES qrepo_folders(id) ON DELETE CASCADE
);

CREATE TABLE qrepo_folder_access (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    folder_id INT NOT NULL,
    include_subfolders TINYINT(1) DEFAULT 0,
    UNIQUE KEY (user_id, folder_id),
    FOREIGN KEY (user_id) REFERENCES qrepo_users(id) ON DELETE CASCADE,
    FOREIGN KEY (folder_id) REFERENCES qrepo_folders(id) ON DELETE CASCADE
);

CREATE TABLE qrepo_files (
    id INT AUTO_INCREMENT PRIMARY KEY,
    folder_id INT NOT NULL,
    uploaded_by INT DEFAULT NULL,
    title VARCHAR(300) NOT NULL,
    filename VARCHAR(300) NOT NULL,
    original_name VARCHAR(300) NOT NULL,
    file_size INT DEFAULT 0,
    mime_type VARCHAR(100) DEFAULT NULL,
    year YEAR DEFAULT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (folder_id) REFERENCES qrepo_folders(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES qrepo_users(id) ON DELETE SET NULL
);
```

## Roles & Permissions

| Role | View | Upload (assigned folders) | Upload (any folder) | Manage content | Manage users |
|------|------|--------------------------|---------------------|----------------|--------------|
| Viewer | ✓ | | | | |
| Submitter | ✓ | ✓ | | | |
| Resource Manager | ✓ | ✓ | ✓ | ✓ | |
| Admin | ✓ | ✓ | ✓ | ✓ | ✓ |

Users can hold multiple roles simultaneously; the highest role is used for display.

## Microsoft OAuth2 Setup

1. Register an app at [Azure Portal](https://portal.azure.com) → Azure Active Directory → App registrations
2. Add a redirect URI: `https://your-domain/qrepo/auth/microsoft/callback.php`
3. Copy the Client ID, Client Secret, and Tenant ID into `includes/config.php`

## Project Structure

```
qrepo/
├── admin/              # Admin panel (users, folders, files)
│   └── ajax/           # AJAX endpoints
├── assets/             # Static assets (logo, PDF.js)
├── auth/microsoft/     # Microsoft OAuth2 callback
├── includes/           # Core: config, db, auth helpers
├── uploads/            # Uploaded files (excluded from git)
├── index.php           # Main file browser
├── login.php           # Login page
├── submit.php          # File upload page
├── view.php            # File detail page
└── viewer.php          # Inline PDF viewer
```

## License

MIT
