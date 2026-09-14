(function () {
  "use strict";

  // Mobiles Menü öffnen/schließen
  var toggle = document.querySelector(".nav-toggle");
  var nav = document.getElementById("main-nav");

  if (toggle && nav) {
    toggle.addEventListener("click", function () {
      var isOpen = nav.classList.toggle("is-open");
      toggle.setAttribute("aria-expanded", isOpen ? "true" : "false");
    });

    nav.querySelectorAll("a").forEach(function (link) {
      link.addEventListener("click", function () {
        nav.classList.remove("is-open");
        toggle.setAttribute("aria-expanded", "false");
      });
    });
  }

  // Sanftes Einblenden beim Scrollen
  var revealEls = document.querySelectorAll(".reveal");
  var prefersReducedMotion = window.matchMedia(
    "(prefers-reduced-motion: reduce)"
  ).matches;

  if ("IntersectionObserver" in window && !prefersReducedMotion) {
    var observer = new IntersectionObserver(
      function (entries) {
        entries.forEach(function (entry) {
          if (entry.isIntersecting) {
            entry.target.classList.add("is-visible");
            observer.unobserve(entry.target);
          }
        });
      },
      { threshold: 0.15 }
    );

    revealEls.forEach(function (el) {
      observer.observe(el);
    });
  } else {
    revealEls.forEach(function (el) {
      el.classList.add("is-visible");
    });
  }

  // Kontaktformular per fetch versenden, ohne die Seite neu zu laden
  var form = document.getElementById("contact-form");
  if (form) {
    var statusBox = document.getElementById("form-status");
    var submitBtn = form.querySelector('button[type="submit"]');

    form.addEventListener("submit", function (event) {
      event.preventDefault();

      submitBtn.disabled = true;
      submitBtn.textContent = "Wird gesendet …";
      statusBox.hidden = true;
      statusBox.className = "form-status";

      fetch(form.action, {
        method: "POST",
        body: new FormData(form),
        headers: { Accept: "application/json" },
      })
        .then(function (response) {
          return response.json().catch(function () {
            return { success: false };
          });
        })
        .then(function (data) {
          statusBox.hidden = false;
          if (data.success) {
            statusBox.classList.add("success");
            statusBox.textContent =
              "Danke für deine Nachricht! Ich melde mich so schnell wie möglich bei dir.";
            form.reset();
          } else {
            statusBox.classList.add("error");
            statusBox.textContent =
              data.message ||
              "Da ist leider etwas schiefgegangen. Schreib mir gern direkt per E-Mail.";
          }
        })
        .catch(function () {
          statusBox.hidden = false;
          statusBox.classList.add("error");
          statusBox.textContent =
            "Da ist leider etwas schiefgegangen. Schreib mir gern direkt per E-Mail.";
        })
        .finally(function () {
          submitBtn.disabled = false;
          submitBtn.textContent = "Nachricht senden";
        });
    });
  }
})();
