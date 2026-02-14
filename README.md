# 🏨 Hotel Room Service & Reservation System - Group 3

Welcome to the **Hotel Room Service & Reservation System**, a comprehensive web application designed to streamline hotel operations and enhance guest experience. This project was developed as a group assignment for the **Web Programming** course.

---

## 👥 Kelompok 3 TI23B :
*   **Ayu Rianti (230511037)**
*   **Muhammad Abdullah Fikri (230511048)** 
*   **Surya Hidayat (230511055)**


---

## 🌟 About The Project
This project is an integrated platform for managing hotel reservations and room services. It features a modern, responsive design and provides specialized dashboards for different user roles, ensuring efficient interaction between guests and hotel staff.

### Key Features:
*   **Multi-Role Access**: Separate interfaces for Admin, Receptionist, and Customers.
*   **Real-time Availability**: Check room status and availability in real-time.
*   **Online Booking**: Seamless room reservation process for guests.
*   **Room Service Management**: Manage food, laundry, and other services efficiently.
*   **Dynamic Dashboard**: Interactive data visualization for management.
*   **Responsive Design**: Optimized for both desktop and mobile viewing.

---

## 💻 Tech Stack
This project is built using a modern and robust technology stack:

| Language/Technology | Usage |
| :--- | :--- |
| **PHP 8.x** | Core backend logic and server-side processing. |
| **MySQL / MariaDB** | Relational database management. |
| **JavaScript (ES6+)** | Frontend interactivity and AJAX requests. |
| **HTML5 & CSS3** | Structural layout and premium styling (Vanilla CSS). |
| **FontAwesome** | Professional iconography. |
| **Google Fonts** | Modern typography (Poppins). |

---

## 🚀 Getting Started

To run this project locally, follow these steps:

### Prerequisites
1.  **XAMPP** (or any PHP & MySQL environment).
2.  **Web Browser** (Chrome, Firefox, or Edge).

### Installation & Setup
1.  **Clone the Repository**:
    ```bash
    git clone https://github.com/your-username/your-repo-name.git
    ```
2.  **Move to Web Directory**:
    Move the `hotel` folder to your `C:\xampp\htdocs\` directory.

3.  **Database Configuration**:
    *   Open XAMPP Control Panel and start **Apache** and **MySQL**.
    *   Go to [http://localhost/phpmyadmin/](http://localhost/phpmyadmin/).
    *   Create a new database named `db_hotel_complete`.
    *   **Import** the database file: `db_hotel_complete.sql` located in the project root.

4.  **Configuration Check**:
    Ensure `config.php` has the correct database credentials:
    ```php
    define('DB_HOST', 'localhost');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('DB_NAME', 'db_hotel_complete');
    ```

5.  **Run the Application**:
    Open your browser and navigate to:
    `http://localhost/hotel/`

---

## 📂 Project Structure
*   `admin/`: Dashboard and tools for system administrators.
*   `receptionist/`: Modules for check-in/out and room management.
*   `customer/`: Guest-facing features and booking history.
*   `ajax/`: Asynchronous backend handlers.
*   `includes/`: Reusable components and configuration.
*   `assets/`: Images, CSS, and JS files.

---

## 📄 License
Tugas Kelompok - Matakuliah Pemrograman Web © 2026.
