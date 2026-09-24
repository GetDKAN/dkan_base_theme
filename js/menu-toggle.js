/**
 * @param Drupal
 * @param once
 * @file
 * Toggles the header panel (primary and account menus) on narrow screens.
 *
 * The button swaps its icons through aria-expanded (CSS). Escape, clicks
 * outside the header and focus leaving the panel close it (the open sheet
 * covers the search row, so Tab must not land behind it); Escape returns
 * focus to the button.
 */

(function (Drupal, once) {
  Drupal.behaviors.dbtMenuToggle = {
    attach(context) {
      once("dbt-menu-toggle", "[data-dbt-menu-toggle]", context).forEach(
        (button) => {
          const panel = document.getElementById(
            button.getAttribute("aria-controls"),
          );
          if (!panel) {
            return;
          }
          const isOpen = () => button.getAttribute("aria-expanded") === "true";
          const setOpen = (open) => {
            button.setAttribute("aria-expanded", String(open));
            panel.classList.toggle("is-open", open);
          };
          button.addEventListener("click", () => setOpen(!isOpen()));
          const closeWhenFocusLeaves = (event) => {
            const next = event.relatedTarget;
            if (
              isOpen() &&
              next &&
              !panel.contains(next) &&
              !button.contains(next)
            ) {
              setOpen(false);
            }
          };
          panel.addEventListener("focusout", closeWhenFocusLeaves);
          button.addEventListener("focusout", closeWhenFocusLeaves);
          document.addEventListener("keydown", (event) => {
            if (event.key === "Escape" && isOpen()) {
              setOpen(false);
              button.focus();
            }
          });
          document.addEventListener("click", (event) => {
            if (
              isOpen() &&
              !panel.contains(event.target) &&
              !button.contains(event.target)
            ) {
              setOpen(false);
            }
          });
        },
      );
    },
  };
})(Drupal, once);
