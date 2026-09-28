(() => {
    const toggleButton = document.getElementById('admin-sidebar-toggle');
    const sidebar = document.getElementById('admin-sidebar');

    if (!toggleButton || !sidebar) {
        return;
    }

    const isOpen = () => document.documentElement.dataset.toggled === 'open';

    const setOpen = (open) => {
        document.documentElement.dataset.toggled = open ? 'open' : 'close';
    };

    toggleButton.addEventListener('click', () => {
        setOpen(!isOpen());
    });

    document.addEventListener('click', (event) => {
        const clickedInsideSidebar = sidebar.contains(event.target);
        const clickedToggle = toggleButton.contains(event.target);

        if (isOpen() && !clickedInsideSidebar && !clickedToggle) {
            setOpen(false);
        }
    });
})();
