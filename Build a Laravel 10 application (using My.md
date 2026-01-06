Build a Laravel 10 application (using MySQL) named “TimerGIF App” with the following features and structure:

🧱 1. Overview
This app allows registered users to create and manage animated Countdown Counters in GIF format.

Each user can embed their generated GIF on their own website using a public <img> URL.
Users can:
Register and log in securely.
Create timers of two types (countdown to date/time or fixed-duration).
Choose from 25+ visual timer designs (“skins”).
Add optional text above or below the timer.
Get a public embed URL (e.g., https://yourdomain.com/timers/12/abc123.gif).

🧑‍💻 2. Tech Stack
Framework: Laravel 10 (or 11 if available)
Database: MySQL
Frontend: Bootstrap 5 (simple UI)
Authentication: Laravel Breeze (email + password)
GIF Generation: GD Library or FFMpeg
Caching: Laravel Cache (file or Redis) for performance
File Storage:/public/timers/{user_id}/ (no storage:link required)

🗄️ 3. Database Schema
users
id
name
email
password
timestamps
timers
id
user_id (foreign key)
title
timer_type (enum: fixed_date, reload_based)
target_datetime (nullable, for fixed_date)
duration_seconds (nullable, for reload_based)
skin (string, selected timer design)
text_position (enum: top, bottom, none)
custom_text (nullable)
gif_url (string, full public URL to generated GIF)
cache_key (nullable, for caching mechanism)
status (active/inactive)
timestamps

⚙️ 4. Timer Types
1. Fixed Date Timer
Counts down to a specific date and time.
Once expired, displays “Time’s Up” or a static frame.
2. Reload-Based Timer
Always starts from a fixed duration (e.g., 10 minutes) when the page is loaded or refreshed.
Both types dynamically generate a countdown GIF.

🖼️ 5. Timer Skins
Store multiple design templates (at least 25) in resources/timerskins/.
Each defines font style, background, color scheme, etc.
Provide small preview images for each skin.
Allow the user to select one during timer creation.

🧩 6. Timer Creation Flow
User logs in and visits the Dashboard.
Clicks “Create Timer.”
Selects:
Timer type (fixed_date or reload_based)
Target date/time or duration
Timer skin
Optional custom text and position (top, bottom, or none)
When saved:
The app generates the timer GIF and stores it under:

/public/timers/{user_id}/{unique_filename}.gif
Generates a public URL, for example:

https://yourdomain.com/timers/12/abc123.gif
Saves that URL to the database.
The app displays an embed code for users to copy:

<img src="https://yourdomain.com/timers/12/abc123.gif" alt="Countdown Counter">

💾 7. GIF Storage & Permissions
Save files under:

public/timers/{user_id}/
Automatically create the directory if it doesn’t exist:

mkdir(public_path("timers/{$userId}"), 0755, true);
Files are instantly public; no need for php artisan storage:link.
Add /public/timers/ to .gitignore to prevent user data from being committed.

⚡ 8. GIF Caching Mechanism
To improve performance and avoid regenerating the GIF on every request:
Each timer gets a unique cache key like timer_{id}_hash.
When /timers/{user_id}/{filename}.gif is requested:
If a cached GIF exists → serve immediately.
If not cached or expired → regenerate it using TimerGifGenerator.
Store the generated GIF in cache for:
Fixed-date timers: until next minute tick or when expired.
Reload-based timers: about 60 seconds.

🧠 9. GIF Generation Service
Create a service class:
app/Services/TimerGifGenerator.php
Responsibilities:
Generate animated countdown GIF frames.
Apply the chosen skin’s style.
Overlay optional custom text (above or below).
Save final GIF in /public/timers/{user_id}/.
Return the full public URL.

🌐 10. Public Access Endpoint
Create a public route:

GET /timers/{user_id}/{filename}.gif
This route:
Requires no authentication.
Fetches the GIF from /public/timers/{user_id}/.
Uses caching (if enabled).
Responds with correct headers:

return response()->file($path, ['Content-Type' => 'image/gif']);

📋 11. User Dashboard
Authenticated users can:
View all their created timers.
Edit or delete timers.
Preview each timer.
Copy the <img> embed code.
Blade templates needed:
auth/login.blade.php
auth/register.blade.php
dashboard/index.blade.php
timers/create.blade.php
timers/edit.blade.php
timers/preview.blade.php

🧩 12. Application Structure
Controllers:
AuthController → handles user authentication (via Breeze).
TimerController → CRUD operations for timers.
GifController → serves GIFs and manages cache logic.
Service:
App\Services\TimerGifGenerator → generates and styles countdown GIFs.
Models:
User
Timer

🚀 13. Expected Output
Cursor should generate:
All models, migrations, controllers, and service classes.
Blade templates for login, dashboard, create/edit, and preview.
3–5 sample timer skins in resources/timerskins/.
Fully functional public GIF URLs at:

https://yourdomain.com/timers/{user_id}/{filename}.gif
GIF caching for performance.