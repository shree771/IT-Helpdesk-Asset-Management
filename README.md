# IT Helpdesk and Asset Management System

## Project Description

The IT Helpdesk and Asset Management System is a web-based application developed to manage IT support requests and organizational IT assets. The system provides a centralized platform for employees to report technical issues, administrators to manage support activities, and technicians to handle assigned tickets.

## Technologies Used

- HTML
- CSS
- JavaScript
- PHP
- MySQL
- XAMPP
- Apache
- phpMyAdmin

## Main Features

- Role-based login and authentication
- Administrator dashboard
- Employee management
- Technician management
- Support ticket creation
- Ticket assignment
- Ticket status tracking
- Technician comments and resolution notes
- IT asset management
- Report generation
- PDF and Excel report export

## User Roles

### Administrator

The administrator can:

- Manage employees
- Manage technicians
- Manage support tickets
- Assign tickets to technicians
- Manage IT assets
- Generate reports

### Employee

Employees can:

- Create support tickets
- Provide issue details
- View their reported tickets
- Track ticket status

### Technician

Technicians can:

- View assigned tickets
- Update ticket status
- Add technician comments
- Add resolution details

## Ticket Status

The system supports the following ticket statuses:

- Open
- In Progress
- Resolved
- Closed

## Database

The application uses MySQL with the following main tables:

- `users`
- `employees`
- `technicians`
- `tickets`
- `assets`

## Project Structure

```text
IT-Helpdesk-Asset-Management/
│
├── admin/
├── employee/
├── technician/
├── config/
├── assets/
├── login.php
└── ...
