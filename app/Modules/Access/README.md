# Access control

The application currently uses the global system roles `OWNER`, `ADMIN` and
`MEMBER`. Their effective permission matrix is visible at `/admin/access` and
from each member on the Team page. Individual permission editing and custom
roles are intentionally not supported.

Phase 21 reuses the existing `settings.view`, `settings.manage`,
`members.view` and `members.manage` permissions. OWNER has all four. ADMIN can
view and manage operational settings and view the team, but cannot mutate
members. MEMBER has no general Settings or Team access. Domain-specific pages
continue to enforce their own existing permissions.

Frontend navigation hides unavailable links for usability only. Laravel Gates,
active Tenant membership checks and Tenant-scoped queries remain authoritative.
