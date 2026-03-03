## Checklist
- [ ] No `get_current_user_id()` in module/admin code (use `PortalAuth::staff_id()`)
- [ ] No `current_user_can()` in module/admin code (use `PortalAuth::is_logged_in()`)
- [ ] No `is_user_logged_in()` in module/admin code (use `PortalAuth::is_logged_in()`)
- [ ] All nonces use `'hm_nonce'` (not `'hearmed_nonce'`)
- [ ] No duplicate AJAX action registrations
- [ ] All new data stored in PostgreSQL (not wp_options/wp_usermeta)
