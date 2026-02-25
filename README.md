🚀 Automated Attendance & Faculty Schedule Management System
📌 Slide 1: Title Slide
Project Name: Automated Attendance & Faculty Schedule Management System
Developed By: Yash Doifode
Technologies Used: PHP, HTML, CSS, JavaScript, Python (QR Code Generation)
📌 Slide 2: Introduction
🎯 Objective:
To develop a web-based system that automates attendance marking using QR codes and manages faculty schedules efficiently.

✅ Key Features:

QR code-based attendance marking
Faculty schedule management
Role-based access (Admin, Faculty, Students)
Notification system for students
Secure digital footprint tracking
📌 Slide 3: System Overview
🖥️ Users & Roles:
1️⃣ Admin

Manages faculty schedules
Sends notifications
Generates attendance reports
2️⃣ Faculty
Views assigned schedules
Generates QR codes for attendance
3️⃣ Students
Scans QR codes for attendance
Views their attendance records
📌 Slide 4: Technologies Used
🛠️ Frontend:

HTML, CSS, JavaScript (UI Design)
🛠️ Backend:

PHP (Core logic & database interaction)
MySQL (Database for schedules & attendance)
🛠️ Additional Components:

Python (QR Code Generation)
JavaScript (Digital Footprint Tracking)
📌 Slide 5: Faculty Schedule Management
📅 How It Works?

Admin assigns lecture schedules to faculty
Faculty cannot modify schedules (only view them)
Displayed in a calendar format for easy tracking
📌 Slide 6: Student Attendance System
📝 Manual Attendance:

Faculty can mark attendance manually
📸 Automated Attendance:

Faculty generates a QR code for a lecture
Students scan the QR code using their device
Attendance is recorded in real-time
📌 Slide 7: QR Code-Based Attendance System
🔍 How It Works?
1️⃣ Faculty generates a QR code for a specific lecture
2️⃣ The QR code contains:

Faculty ID
Course ID & Subject ID
Today's date
3️⃣ Students scan the QR code to mark attendance
4️⃣ The system verifies the scanned data & records attendance
📌 Slide 8: Digital Footprint Tracking
🔐 Why It’s Important?

Tracks student activity for security
Helps in fraud detection (fake attendance prevention)
🖥️ Collected Data:

User agent, device type, screen resolution
Plugins, browser information, timezone
Digital DNA Hash for unique identification
📌 Slide 9: Notification System
📢 Admin can send notifications related to:

Class schedules
Exam dates
Attendance updates
Important announcements
💬 Notifications are filtered based on:

Course
Semester
Session
📌 Slide 10: Security Features
🔒 Implemented Security Measures:
✅ Role-Based Access Control – Prevents unauthorized actions
✅ Digital DNA Hashing – Tracks device identity
✅ Input Validation & Sanitization – Prevents SQL injection & XSS
✅ Secure QR Code Generation – Unique codes generated per session

📌 Slide 11: Future Enhancements
🚀 Planned Upgrades:
🔹 Face Recognition for Attendance 📸
🔹 AI-Based Fraud Detection 🤖
🔹 Mobile App for Easy Access 📱
🔹 Real-Time Analytics Dashboard 📊

📌 Slide 12: Conclusion
💡 Project Benefits:
✔️ Reduces manual work for faculty
✔️ Prevents proxy attendance
✔️ Increases efficiency with automated scheduling
✔️ Provides a secure and scalable system

🎯 Final Thoughts:
This system enhances attendance tracking and faculty schedule management using modern technologies, ensuring security and efficiency.

## 🚀 Installation & Setup

1. Clone the repository:
   ```bash
   git clone https://github.com/YashDoifode1/SysAttendance_WIth_QR
   cd SysAttendance_WIth_QR   ```

2. Import the database:
   - Locate `attendance_system.sql` in the `database/` folder.
   - Import it into your MySQL database.

3. Configure the database connection:
   - Edit `config/db.php` and update your **DB_HOST, DB_USER, DB_PASS, and DB_NAME**.

4. Start the server:
   ```bash
   php -S localhost:8000
   ```

5. Open your browser and visit:
   ```
   http://localhost:8000
   ```

## 👥 User Roles

- **Admin**: Manages faculty, students, schedules, notifications, and attendance records.
- **Faculty**: Generates QR codes and manages student attendance.
- **Student**: Scans QR codes to mark their attendance.

## 📜 License

This project is licensed under the **MIT License**. Feel free to modify and enhance it as per your needs.

## 🤝 Contributing

Contributions are welcome! To contribute:
- Fork the repository
- Create a new branch (`feature-xyz`)
- Commit your changes
- Create a pull request

## 📩 Contact
For any issues or suggestions, feel free to reach out:
📧 Email: [skidde7@gmail.com](mailto:your.email@example.com)  


---
_Developed with ❤️ by [Yash Doifode]_

