// TABS
document.querySelectorAll('.admin-tab').forEach(tab => {
    tab.addEventListener('click', () => {
        document.querySelectorAll('.admin-tab').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
        tab.classList.add('active');
        document.getElementById('tab-' + tab.dataset.tab).classList.add('active');
    });
});

// SEARCH EQUIPMENT
document.getElementById('equipmentSearch').addEventListener('input', function() {
    const q = this.value.toLowerCase();
    document.querySelectorAll('#equipmentTable tbody tr').forEach(row => {
        row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
});

// SEARCH USERS
document.getElementById('userSearch').addEventListener('input', function() {
    const q = this.value.toLowerCase();
    document.querySelectorAll('#usersTable tbody tr').forEach(row => {
        row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
});

// POST to admin_actions.php
function adminAction(data) {
    return fetch('admin_actions.php', { method: 'POST', body: new FormData(Object.assign(new FormData(), data)) })
        .then(res => res.json());
}

function post(data) {
    const form = new FormData();
    Object.entries(data).forEach(([k, v]) => form.append(k, v));
    return fetch('admin_actions.php', { method: 'POST', body: form }).then(r => r.json());
}

// TOGGLE AVAILABILITY
document.querySelectorAll('.toggle-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        post({ action: 'toggle_availability', id: btn.dataset.id, current_status: btn.dataset.status })
            .then(res => {
                if (res.success) location.reload();
            });
    });
});

// ADD EQUIPMENT
document.getElementById('openAddEquipment').addEventListener('click', () => {
    document.getElementById('addEquipmentModal').classList.add('show');
});
document.getElementById('cancelAddEquipment').addEventListener('click', () => {
    document.getElementById('addEquipmentModal').classList.remove('show');
});
document.getElementById('confirmAddEquipment').addEventListener('click', () => {
    const msg = document.getElementById('addEquipmentMsg');
    post({
        action:       'add_equipment',
        name:         document.getElementById('addName').value.trim(),
        brand:        document.getElementById('addBrand').value.trim(),
        model:        document.getElementById('addModel').value.trim(),
        category:     document.getElementById('addCategory').value,
        description:  document.getElementById('addDescription').value.trim(),
        daily_rate:   document.getElementById('addDaily').value,
        weekly_rate:  document.getElementById('addWeekly').value,
        image_url:    document.getElementById('addImage').value.trim()
    }).then(res => {
        msg.className = 'message ' + (res.success ? 'success' : 'error');
        msg.textContent = res.success ? 'Equipment added successfully!' : 'Failed. Try again.';
        if (res.success) setTimeout(() => location.reload(), 1500);
    });
});

// EDIT EQUIPMENT
document.querySelectorAll('.edit-eq-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.getElementById('editEqId').value         = btn.dataset.id;
        document.getElementById('editName').value         = btn.dataset.name;
        document.getElementById('editBrand').value        = btn.dataset.brand;
        document.getElementById('editModel').value        = btn.dataset.model;
        document.getElementById('editDescription').value  = btn.dataset.description;
        document.getElementById('editDaily').value        = btn.dataset.daily;
        document.getElementById('editWeekly').value       = btn.dataset.weekly;
        document.getElementById('editImage').value        = btn.dataset.image;
        document.getElementById('editCategory').value     = btn.dataset.category;
        document.getElementById('editEquipmentModal').classList.add('show');
    });
});
document.getElementById('cancelEditEquipment').addEventListener('click', () => {
    document.getElementById('editEquipmentModal').classList.remove('show');
});
document.getElementById('confirmEditEquipment').addEventListener('click', () => {
    const msg = document.getElementById('editEquipmentMsg');
    post({
        action:      'edit_equipment',
        id:          document.getElementById('editEqId').value,
        name:        document.getElementById('editName').value.trim(),
        brand:       document.getElementById('editBrand').value.trim(),
        model:       document.getElementById('editModel').value.trim(),
        category:    document.getElementById('editCategory').value,
        description: document.getElementById('editDescription').value.trim(),
        daily_rate:  document.getElementById('editDaily').value,
        weekly_rate: document.getElementById('editWeekly').value,
        image_url:   document.getElementById('editImage').value.trim()
    }).then(res => {
        msg.className = 'message ' + (res.success ? 'success' : 'error');
        msg.textContent = res.message;
        if (res.success) setTimeout(() => location.reload(), 1500);
    });
});

// DELETE EQUIPMENT
let pendingDelete = null;
document.querySelectorAll('.delete-eq-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        pendingDelete = { action: 'delete_equipment', id: btn.dataset.id };
        document.getElementById('confirmText').textContent = `Delete "${btn.dataset.name}"? This cannot be undone.`;
        document.getElementById('confirmModal').classList.add('show');
    });
});

// CREATE USER
document.getElementById('openCreateUser').addEventListener('click', () => {
    // Clear fields each time modal opens
    document.getElementById('newUserName').value     = '';
    document.getElementById('newUserEmail').value    = '';
    document.getElementById('newUserPassword').value = '';
    document.getElementById('newUserRole').value     = '';
    document.getElementById('createUserMsg').className = 'message';
    document.getElementById('createUserMsg').textContent = '';
    document.getElementById('createUserModal').classList.add('show');
});

document.getElementById('cancelCreateUser').addEventListener('click', () => {
    document.getElementById('createUserModal').classList.remove('show');
});

document.getElementById('confirmCreateUser').addEventListener('click', () => {
    const msg      = document.getElementById('createUserMsg');
    const name     = document.getElementById('newUserName').value.trim();
    const email    = document.getElementById('newUserEmail').value.trim();
    const password = document.getElementById('newUserPassword').value;
    const role     = document.getElementById('newUserRole').value;

    if (!name || !email || !password || !role) {
        msg.className = 'message error';
        msg.textContent = 'Please fill in all fields.';
        return;
    }
    if (password.length < 6) {
        msg.className = 'message error';
        msg.textContent = 'Password must be at least 6 characters.';
        return;
    }

    post({
        action:    'create_user',
        full_name: name,
        email:     email,
        password:  password,
        role:      role
    }).then(res => {
        msg.className = 'message ' + (res.success ? 'success' : 'error');
        msg.textContent = res.message;
        if (res.success) setTimeout(() => location.reload(), 1500);
    }).catch(() => {
        msg.className = 'message error';
        msg.textContent = 'Could not connect. Try again.';
    });
});

// Password toggle for create user modal
document.querySelectorAll('.toggle-password').forEach(button => {
    button.addEventListener('click', () => {
        const target = document.getElementById(button.dataset.target);
        if (!target) return;
        const isPassword = target.type === 'password';
        target.type = isPassword ? 'text' : 'password';
        button.textContent = isPassword ? 'Hide' : 'Show';
    });
});

// SUSPEND USER
document.querySelectorAll('.suspend-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        post({ action: 'toggle_suspend', id: btn.dataset.id, current_status: btn.dataset.status })
            .then(res => { if (res.success) location.reload(); });
    });
});

// EDIT USER
document.querySelectorAll('.edit-user-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.getElementById('editUserId').value    = btn.dataset.id;
        document.getElementById('editUserName').value  = btn.dataset.name;
        document.getElementById('editUserEmail').value = btn.dataset.email;
        document.getElementById('editUserRole').value  = btn.dataset.role;
        document.getElementById('editUserModal').classList.add('show');
    });
});
document.getElementById('cancelEditUser').addEventListener('click', () => {
    document.getElementById('editUserModal').classList.remove('show');
});
document.getElementById('confirmEditUser').addEventListener('click', () => {
    const msg = document.getElementById('editUserMsg');
    post({
        action:    'edit_user',
        id:        document.getElementById('editUserId').value,
        full_name: document.getElementById('editUserName').value.trim(),
        email:     document.getElementById('editUserEmail').value.trim(),
        role:      document.getElementById('editUserRole').value
    }).then(res => {
        msg.className = 'message ' + (res.success ? 'success' : 'error');
        msg.textContent = res.message;
        if (res.success) setTimeout(() => location.reload(), 1500);
    });
});

// DELETE USER
document.querySelectorAll('.delete-user-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        pendingDelete = { action: 'delete_user', id: btn.dataset.id };
        document.getElementById('confirmText').textContent = `Delete user "${btn.dataset.name}"? All their bookings will also be removed.`;
        document.getElementById('confirmModal').classList.add('show');
    });
});

// CONFIRM DELETE
document.getElementById('confirmYes').addEventListener('click', () => {
    if (!pendingDelete) return;
    post(pendingDelete).then(res => {
        if (res.success) location.reload();
    });
});
document.getElementById('confirmNo').addEventListener('click', () => {
    document.getElementById('confirmModal').classList.remove('show');
    pendingDelete = null;
});

// VIEW USER BOOKINGS
document.querySelectorAll('.view-bookings-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.getElementById('bookingsUserName').textContent = btn.dataset.name;
        document.getElementById('bookingsContent').innerHTML = 'Loading...';
        document.getElementById('bookingsModal').classList.add('show');

        post({ action: 'get_bookings', user_id: btn.dataset.id })
            .then(res => {
                if (!res.bookings || res.bookings.length === 0) {
                    document.getElementById('bookingsContent').innerHTML = '<p>No bookings found for this user.</p>';
                    return;
                }
                let html = '<table class="admin-table"><thead><tr><th>Equipment</th><th>Start</th><th>End</th><th>Total</th><th>Status</th></tr></thead><tbody>';
                res.bookings.forEach(b => {
                    html += `<tr>
                        <td>${b.name} (${b.brand})</td>
                        <td>${b.start_date}</td>
                        <td>${b.end_date}</td>
                        <td>£${parseFloat(b.total_price).toFixed(2)}</td>
                        <td><span class="badge ${b.status === 'Confirmed' ? 'badge-green' : 'badge-red'}">${b.status}</span></td>
                    </tr>`;
                });
                html += '</tbody></table>';
                document.getElementById('bookingsContent').innerHTML = html;
            });
    });
});
document.getElementById('closeBookings').addEventListener('click', () => {
    document.getElementById('bookingsModal').classList.remove('show');
});