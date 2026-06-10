export function formatRole(role) {
  return role.replace('ROLE_', '').replace('_', ' ');
}

export function roleBadgeClass(role) {
  return role === 'ROLE_ADMIN'
    ? 'border-accent/25 bg-accent-soft text-accent'
    : 'border-edge bg-panel-2 text-ink-mute';
}
