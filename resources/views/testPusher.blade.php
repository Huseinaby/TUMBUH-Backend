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
            authEndpoint: 'https://tumbuh.vps-poliban.my.id/api/broadcasting/auth',
            auth: {
                headers: {
                    Authorization: 'Bearer 15|5FlmLzyU5QZxD68lYK52axSBUmktcoMTiZeGnh817d82e74d',
                }
            }
        });

        // Subscribe ke private channel
        const channel = pusher.subscribe('private-user.4');

        // Cek apakah berhasil subscribe
        channel.bind('pusher:subscription_succeeded', () => {
            console.log('✅ Subscribed to private-user.4');
        });

        // Handle subscription error
        channel.bind('pusher:subscription_error', (error) => {
            console.error('❌ Subscription error:', error);
        });

        // Tangkap event user.notification (HAPUS TITIK DI AWAL)
        channel.bind('user.notification', (data) => {
            console.log('🔔 Notification received:', data);
            const li = document.createElement('li');
            li.textContent = `${data.type}: ${data.message}`;
            li.style.marginBottom = '10px';
            li.style.padding = '10px';
            li.style.backgroundColor = data.type === 'error' ? '#ffebee' : '#e8f5e8';
            li.style.border = `1px solid ${data.type === 'error' ? '#f44336' : '#4caf50'}`;
            li.style.borderRadius = '4px';
            document.getElementById('notifications').appendChild(li);
        });

        // Debug: Log semua event yang diterima
        channel.bind_global((eventName, data) => {
            console.log('📡 Event received:', eventName, data);
        });

        // Connection status
        pusher.connection.bind('connected', () => {
            console.log('🔗 Connected to Pusher');
        });

        pusher.connection.bind('disconnected', () => {
            console.log('🔌 Disconnected from Pusher');
        });
    </script>
</body>

</html>
