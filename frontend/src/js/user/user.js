const userViews = {
  "signer-id": "./partials/signer-id.html",
  "entry-data": "./partials/entry-data.html",
  signature: "./partials/signature-thumbprint.html",
  "linked-act": "./partials/linked-act.html",
  "audit-log": "./partials/audit-log.html",
};

document.addEventListener("DOMContentLoaded", async () => {
  await window.PortalLayout.init();
  bindTabs();
  await renderActiveView(getViewFromHash());
  window.addEventListener("hashchange", async () => {
    await renderActiveView(getViewFromHash());
  });
});

function getViewFromHash() {
  const value = window.location.hash.replace("#", "");
  return userViews[value] ? value : "signer-id";
}

function bindTabs() {
  document.querySelectorAll("[data-view]").forEach((button) => {
    button.addEventListener("click", () => {
      window.location.hash = button.dataset.view;
    });
  });
}

async function renderActiveView(viewKey) {
  const container = document.querySelector("#user-screen");
  if (!container) return;

  await window.PortalLayout.loadInto(container, userViews[viewKey]);

  document.querySelectorAll("[data-view]").forEach((button) => {
    button.classList.toggle("is-active", button.dataset.view === viewKey);
  });

  bindViewActions(viewKey);
}

function bindViewActions(viewKey) {
  document.querySelectorAll("[data-nav-view]").forEach((button) => {
    button.addEventListener("click", () => {
      window.location.hash = button.dataset.navView;
    });
  });

  if (viewKey === "signer-id") {
    document
      .querySelector("#discard-signer-view")
      ?.addEventListener("click", () => {
        window.alert("Discarded mock changes.");
      });

    document.querySelectorAll(".method-option").forEach((option) => {
      option.addEventListener("click", () => {
        document
          .querySelectorAll(".method-option")
          .forEach((item) => item.classList.remove("is-active"));
        option.classList.add("is-active");
      });
    });

    document.querySelectorAll("[data-selectable-tile]").forEach((tile) => {
      tile.addEventListener("click", () => {
        tile.classList.toggle("is-selected");
      });
    });
  }

  if (viewKey === "signature") {
    document.querySelectorAll("[data-toggle-complete]").forEach((button) => {
      button.addEventListener("click", () => {
        const target = document.querySelector(button.dataset.toggleComplete);
        target?.classList.toggle("is-complete");
      });
    });
  }

  if (viewKey === "linked-act") {
    document.querySelectorAll("[data-verify-file]").forEach((button) => {
      button.addEventListener("click", () => {
        button.textContent = "Verified";
      });
    });
  }
}
