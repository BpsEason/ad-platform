const express = require('express');
const path = require('path');

const app = express();
const port = process.env.PORT || 80; // Default to 80 for Docker

// Serve static files from the 'dist' directory (Vite's default build output)
app.use(express.static(path.join(__dirname, 'dist')));

// For any other requests, serve the index.html (Vue SPA routing)
app.get('*', (req, res) => {
  res.sendFile(path.join(__dirname, 'dist', 'index.html'));
});

app.listen(port, () => {
  console.log(`Frontend server listening at http://localhost:${port}`);
});
