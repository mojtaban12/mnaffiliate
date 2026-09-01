</div>
    <script src="/mnaffiliate/assets/js/bootstrap.bundle.min.js"></script>
    <script>
        // هایلایت لینک فعال سایدبار
        document.querySelectorAll('.sidebar nav a').forEach(function (a) {
            if (location.pathname === a.getAttribute('href')) a.classList.add('active');
        });
        // تاریخ شمسی امروز
        try {
            document.getElementById('mn-date').textContent =
                'امروز: ' + new Intl.DateTimeFormat('fa-IR', { dateStyle: 'full' }).format(new Date());
        } catch (e) {}
    </script>
</body>
</html>