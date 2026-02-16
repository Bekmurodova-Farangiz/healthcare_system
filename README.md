# Healthcare Management System (Database Project)

This project was developed as part of a **Database Systems course**.
It demonstrates how a relational database can be used to manage a healthcare workflow involving **patients, doctors, appointments, and medical records**.

The system is implemented using **PHP and MySQL**, with role-based access for different users.

---

## Project Objective

The goal of this project is to design and implement a database-driven application that:

* Stores healthcare-related data in a structured relational database
* Supports multiple user roles
* Demonstrates CRUD operations
* Uses authentication with hashed passwords
* Connects a web interface to a MySQL database

---

## Features

### Authentication Module

* User registration
* Login/logout
* Password hashing

### Patient Module

* Book appointments
* View doctors
* Access medical records
* Submit feedback

### Doctor Module

* Doctor dashboard
* Prescribe functionality

### Admin Module

* Admin dashboard

---

## Database Concepts Demonstrated

* Relational schema design
* Primary and foreign keys
* CRUD operations
* Data integrity
* Database connection handling in PHP

---

## Technologies Used

* PHP
* MySQL
* HTML/CSS
* Apache (XAMPP/MAMP)

---

## Project Structure

```
HEALTHCARE_V2/
├── admin/
├── auth/
├── doctors/
├── includes/
│   └── db.php
├── patients/
└── generate_hash.php
```

---

## How to Run the Project

1. Clone the repository
2. Move project into `htdocs`
3. Create the database in MySQL
4. Update database credentials in `includes/db.php`
5. Start Apache and MySQL
6. Open in browser:

```
http://localhost/HEALTHCARE_V2
```

---

## Course Information

Course: Database Systems
Project Type: Academic Project

---

## Author

Your Name
