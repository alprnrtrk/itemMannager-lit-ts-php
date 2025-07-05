# itemManager-lit-ts-php: Modern Frontend Meets Versatile Backend

## 🌟 Vision: "Develop Modern, Run Authentic"

The core philosophy behind `itemManager-lit-ts-php` is to bridge the gap between contemporary frontend development paradigms and the widespread accessibility of traditional PHP server environments. This project exemplifies a unique and powerful trait: **a cutting-edge Lit and TypeScript frontend meticulously crafted to run seamlessly on virtually any standard PHP server.**

This groundbreaking approach allows developers to leverage the best of modern reactive UI development without being tethered to complex Node.js-centric server setups for deployment. It's about achieving powerful, dynamic web applications with unparalleled deployment flexibility and efficiency.

## ✨ Project Overview

`itemManager-lit-ts-php` is a robust demonstration of a Single-Page Application (SPA) built with Lit and TypeScript, coupled with a lightweight PHP backend for data persistence and API handling. It features an administrative panel for comprehensive CRUD (Create, Read, Update, Delete) operations on items, including image management.

This project is an ideal starting point for developers looking to:
* Learn modern web component development with Lit.
* Understand full-stack integration between a modern frontend and a PHP backend.
* Build highly interactive applications that are simple to deploy on shared hosting or traditional LAMP/LEMP stacks.

## 🌈 Key Features

* **Modern Frontend Stack:** Leverages **Lit** for building fast, lightweight, and reusable web components, powered by **TypeScript** for enhanced code quality and maintainability.
* **Rapid Development with Vite:** Utilizes **Vite** as a lightning-fast build tool, providing an exceptional developer experience with instant hot module replacement.
* **Seamless Client-Side Routing:** Implements fluid SPA navigation using `page.js`, ensuring a responsive and intuitive user interface without full page reloads.
* **Versatile PHP Backend:** A lean PHP API handles all server-side logic, demonstrating efficient data processing and file management. **This backend is designed to operate on any standard PHP hosting environment, offering unparalleled deployment simplicity.**
* **Comprehensive CRUD Operations:** Full functionality for adding, viewing, updating, and deleting items through the administrative interface.
* **Secure Admin Authentication:** A basic, yet effective, password-based authentication system secures the admin panel.
* **Intelligent Image Management:** Handles file uploads with validation, stores images securely, and automates the deletion of old images during item updates or removals.
* **File-Based Persistence:** Data is stored efficiently in a `JSON` file (`public/data/items.json`), eliminating the need for a separate database setup for simplified demonstration and deployment.

## 🛠️ Technologies Used

### Frontend:
* **Lit:** For Web Component development.
* **TypeScript:** For type-safe JavaScript.
* **Vite:** For build tooling and development server.
* **`page.js`:** For client-side routing.

### Backend:
* **PHP:** For server-side API handling.

## 🚀 Getting Started

Follow these steps to get your `itemManager-lit-ts-php` project up and running locally.

### Prerequisites

* Node.js (LTS version recommended)
* npm (usually comes with Node.js)
* PHP (7.4 or higher recommended)

### 1. Clone the Repository

First, clone this repository to your local machine:

```bash
git clone https://github.com/alprnrtrk/itemManager-lit-ts-php.git
cd itemManager-lit-ts-php
````

### 2\. Frontend Setup

Navigate into the project directory and install the frontend dependencies:

```bash
npm install
```

### 3\. Backend Setup

Your PHP backend is designed for minimal setup.

  * **Directory Permissions:** Ensure that the `public/data/` and `public/uploads/` directories have write permissions for your web server. For development, `chmod 777 public/data public/uploads` might be used, but for production, consider more restrictive permissions (`775` or appropriate user/group ownership).

  * **Admin Password:**
    Open `public/index.php` and **change the default admin password** on line `~42` (or search for `admin_password_secret`):

    ```php
    // public/index.php
    $admin_password_secret = 'your_strong_admin_password'; // <--- CHANGE THIS!
    ```

    Replace `'your_strong_admin_password'` with a secure password of your choice.

### 4\. Running the Application

You will need to run both the frontend development server (for Lit/TypeScript development with hot-reloading) and a PHP server (to handle API requests).

#### a. Start Frontend Development Server

In your project root, start the Vite development server:

```bash
npm run dev
```

This will typically run on `http://localhost:5173/`. Keep this terminal running.

#### b. Start PHP Development Server

Open a **new terminal** window, navigate to your project root, and start the PHP built-in server, serving the `public` directory:

```bash
php -S localhost:8000 -t public
```

This will run your PHP backend on `http://localhost:8000/`.

### 5\. Accessing the Application

Now, open your web browser and navigate to the **frontend development server URL**:

`http://localhost:5173`

You can then navigate to `/admin` to access the admin panel and test the CRUD functionality after logging in.

## 🤝 Contributing

Contributions are welcome\! If you have suggestions for improvements, new features, or bug fixes, please feel free to open an issue or submit a pull request.
