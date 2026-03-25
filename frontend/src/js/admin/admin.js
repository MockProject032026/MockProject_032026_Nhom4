document.addEventListener("DOMContentLoaded", async () => {
  await window.PortalLayout.init();
  renderRecentLogs();
  bindAdminActions();
});

function renderRecentLogs() {
  const body = document.querySelector("#admin-log-body");
  if (!body) return;

  const rows = [
    {
      id: "#8828",
      notary: "Jane Doe",
      issue: "Signer signature missing",
      updated: "03/25/2026 08:15",
      status: "Open",
      statusClass: "is-open",
    },
    {
      id: "#8819",
      notary: "Robert Fox",
      issue: "Thumbprint review required",
      updated: "03/24/2026 17:40",
      status: "Open",
      statusClass: "is-open",
    },
    {
      id: "#8797",
      notary: "Sarah Jenkins",
      issue: "Certificate reference verified",
      updated: "03/24/2026 15:20",
      status: "Resolved",
      statusClass: "is-resolved",
    },
  ];

  body.innerHTML = rows
    .map(
      (row) => `
        <tr>
          <td><a class="entry-link" href="./notary/notary.html">${row.id}</a></td>
          <td>${row.notary}</td>
          <td>${row.issue}</td>
          <td>${row.updated}</td>
          <td><span class="admin-log-status ${row.statusClass}">${row.status}</span></td>
        </tr>
      `
    )
    .join("");
}

function bindAdminActions() {
  document.querySelector("#admin-clear-filters")?.addEventListener("click", () => {
    window.alert("Filters reset in this mock dashboard.");
  });

  document.querySelector("#admin-new-audit")?.addEventListener("click", () => {
    window.alert("New Audit action is a placeholder in this Live Server prototype.");
  });
}
