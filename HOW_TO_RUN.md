# How to Run AMIMS on a New Computer

No server software needed. Just PHP and a browser.

---

## Step 1 — Install PHP

1. Open **PowerShell** (press `Win + X`, click **Terminal** or **Windows PowerShell**)
2. Paste this command and press Enter:
   ```
   winget install PHP.PHP.8.3
   ```
3. Wait for it to finish. It will say "Successfully installed".
4. **Close PowerShell and open a new one** (important — so it picks up PHP).
5. Check it worked:
   ```
   php -v
   ```
   You should see something like: `PHP 8.3.x ...`

---

## Step 2 — Enable SQLite in PHP

1. Find where PHP was installed. Run this in PowerShell:
   ```
   Get-Command php | Select-Object -ExpandProperty Source
   ```
   It will print a path like:
   `C:\Users\YourName\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.3_...\php.exe`

2. Go to that folder (everything before `php.exe`). Look for a file called **`php.ini`**.
   - If it does not exist, copy `php.ini-development` and rename the copy to `php.ini`.

3. Open `php.ini` in VS Code (or any text editor).

4. Find this line:
   ```
   ;extension=pdo_sqlite
   ```
   Remove the semicolon `;` at the front so it becomes:
   ```
   extension=pdo_sqlite
   ```
   Save the file.

> **Note:** If you don't see `;extension=pdo_sqlite` at all, just add these two lines anywhere under `[PHP]`:
> ```
> extension=pdo_sqlite
> extension=sqlite3
> ```

---

## Step 3 — Run the App

1. Open PowerShell.
2. Navigate to the project folder:
   ```
   cd "C:\path\to\amims"
   ```
   *(Replace `C:\path\to\amims` with the actual folder path where you put the project)*

3. Start the server:
   ```
   php -S 127.0.0.1:8888
   ```
   You should see:
   `PHP Development Server started at http://127.0.0.1:8888`

4. **Leave that PowerShell window open** (closing it stops the server).

---

## Step 4 — Open the App

1. Open any browser (Chrome, Edge, Firefox).
2. Go to:
   ```
   http://127.0.0.1:8888/modules/auth/login.php
   ```
3. Log in with:
   - **Email:** admin@amims.ng
   - **Password:** Admin@1234
   - **Role:** Administrator

Done! You should see the AMIMS dashboard.

---

## To Stop the Server

Go back to the PowerShell window and press `Ctrl + C`.

## To Start Again Next Time

Just repeat Step 3 — navigate to the project folder and run:
```
php -S 127.0.0.1:8888
```

---

## Troubleshooting

| Problem | Fix |
|---|---|
| `php` not recognized | Close and reopen PowerShell after installing |
| "Database connection failed" | Make sure `extension=pdo_sqlite` is in php.ini (Step 2) |
| Page not loading | Make sure the server is still running in PowerShell |
| Wrong password | See `LOGINS.md` for all login details |
