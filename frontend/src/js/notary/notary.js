document.addEventListener("DOMContentLoaded", async () => {
  await window.PortalLayout.init();
  renderJournalRows();
  bindNotaryActions();
});

function renderJournalRows() {
  const body = document.querySelector("#notary-journal-body");
  if (!body) return;

  const rows = [
    {
      id: "#8829",
      date: "Oct 24, 2023",
      time: "10:30 AM",
      notary: "Jane Doe",
      actType: "Acknowledgment",
      signer: "John Smith",
      fee: "$25.00",
      status: "Completed",
      statusClass: "is-complete",
      risk: "None",
      alert: "",
    },
    {
      id: "#8828",
      date: "Oct 24, 2023",
      time: "09:15 AM",
      notary: "Jane Doe",
      actType: "Jurat",
      signer: "Mary Ellis",
      fee: "$15.00",
      status: "Draft",
      statusClass: "is-draft",
      risk: "Warning",
      alert: "warning",
    },
    {
      id: "#8827",
      date: "Oct 23, 2023",
      time: "04:45 PM",
      notary: "Robert Fox",
      actType: "Oaths",
      signer: "Alice Wong",
      fee: "-",
      status: "Locked",
      statusClass: "is-locked",
      risk: "None",
      alert: "",
    },
    {
      id: "#8825",
      date: "Oct 22, 2023",
      time: "11:30 AM",
      notary: "Robert Fox",
      actType: "Deed",
      signer: "Linda White",
      fee: "$25.00",
      status: "Action Required",
      statusClass: "is-warning",
      risk: "Warning",
      alert: "danger",
    },
  ];

  body.innerHTML = rows
    .map(
      (row) => `
        <tr>
          <td>
            <span class="entry-id-cell">
              <span>${row.id}</span>
              ${renderAlert(row.alert)}
            </span>
          </td>
          <td>
            <span>${row.date}</span>
            <span class="table-subtext">${row.time}</span>
          </td>
          <td>${row.notary}</td>
          <td><span class="chip">${row.actType}</span></td>
          <td>${row.signer}</td>
          <td>${row.fee}</td>
          <td><span class="status-badge ${row.statusClass}">${row.status}</span></td>
          <td>${row.risk === "None" ? "None" : '<span class="risk-badge is-warning">Warning</span>'}</td>
          <td><a class="entry-link" href="../user/user.html#signer-id">Details</a></td>
        </tr>
      `
    )
    .join("");
}

function renderAlert(type) {
  if (type === "warning") return '<span class="entry-alert entry-alert--warning">&#9651;</span>';
  if (type === "danger") return '<span class="entry-alert entry-alert--danger">!</span>';
  return "";
}

function bindNotaryActions() {
  document.querySelector("#notary-new-entry")?.addEventListener("click", () => {
    window.location.href = "../user/user.html#signer-id";
  });

  document.querySelector("#notary-export")?.addEventListener("click", () => {
    window.alert("Export CSV is a placeholder in this static mock.");
  });

  document.querySelector("#notary-clear-filters")?.addEventListener("click", () => {
    window.alert("Filters reset in this mock journal list.");
  });
}
