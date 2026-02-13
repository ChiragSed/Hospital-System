const express = require("express");
const fs = require("fs");
const path = require("path");

const app = express();
const PORT = process.env.PORT || 3001;

app.use(express.json());

app.post("/notify", (req, res) => {
  const message = req.body && req.body.message ? req.body.message : "New appointment booked";
  const logLine = `${new Date().toISOString()} - ${message}\n`;

  const logDir = path.join(__dirname, "logs");
  const logFile = path.join(logDir, "notifications.log");

  if (!fs.existsSync(logDir)) {
    fs.mkdirSync(logDir, { recursive: true });
  }

  fs.appendFileSync(logFile, logLine, "utf8");
  console.log(logLine.trim());

  res.json({ success: true, logged: message });
});

app.get("/health", (_req, res) => {
  res.json({ ok: true });
});

app.listen(PORT, () => {
  console.log(`Notification microservice running on port ${PORT}`);
});

