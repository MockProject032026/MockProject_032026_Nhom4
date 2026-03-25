(function () {
  async function loadInto(target, path) {
    const element = typeof target === "string" ? document.querySelector(target) : target;
    if (!element) return null;

    const response = await fetch(path);
    if (!response.ok) {
      element.innerHTML = '<section class="page-card" style="padding:20px;">Failed to load partial.</section>';
      return element;
    }

    element.innerHTML = await response.text();
    return element;
  }

  async function initPartials() {
    const nodes = Array.from(document.querySelectorAll("[data-partial]"));
    await Promise.all(nodes.map((node) => loadInto(node, node.dataset.partial)));
  }

  function highlightNav() {
    const activeKey = document.body.dataset.nav;
    document.querySelectorAll(".portal-menu__link").forEach((link) => {
      link.classList.toggle("is-active", link.dataset.nav === activeKey);
    });
  }

  window.PortalLayout = {
    init: async function init() {
      await initPartials();
      highlightNav();
    },
    loadInto,
  };
})();
