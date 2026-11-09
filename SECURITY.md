# Security policy

Please report vulnerabilities privately through GitHub: **Security > Report a vulnerability** on this repository.
Do not open a public issue for security problems.

Supported: the latest release on `main`.

Notes for integrators: the `html` widget prints author markup verbatim (as Elementor does), so only trusted users should edit
documents. Put the editor routes behind authentication and define the `lamentor.edit` gate. The demo server in `tools/demo`
disables authentication and must never be exposed.
