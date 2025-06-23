<!DOCTYPE html>
<html>
<head>
  <title>Private Channel Demo</title>
  <!-- Load Pusher JS library -->
  <script src="https://js.pusher.com/8.3.0/pusher.min.js"></script>
</head>
<body>
  <h1>Notifikasi Realtime</h1>
  <ul id="notifications"></ul>

  <script>
    // Inisialisasi Pusher
    const pusher = new Pusher('ba8f0597dd4a00b837c1', {
      cluster: 'ap1',
      authEndpoint: 'https://tumbuh.vps-poliban.my.id/broadcasting/auth',
      auth: {
        headers: {
          Authorization: 'Bearer 15|5FlmLzyU5QZxD68lYK52axSBUmktcoMTiZeGnh817d82e74d',
        }
      }
    });

    // Subscribe ke private channel (prefix private- otomatis ditambahkan oleh pusher-js)
    const channel = pusher.subscribe('user.4');

    // Cek apakah berhasil subscribe
    channel.bind('pusher:subscription_succeeded', () => {
      console.log('✅ Subscribed to private-user.4');
    });

    // Tangkap event user.notification
    channel.bind('.user.notification', (data) => {
        console.log('🔔 Notification received:', data);
      const li = document.createElement('li');
      li.textContent = `${data.type}: ${data.message}`;
      document.getElementById('notifications').appendChild(li);
    });
  </script>
</body>
</html>
