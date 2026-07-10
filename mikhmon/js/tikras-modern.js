(function () {
  function tikrasText(value) {
    return (value || "").toString().toLowerCase();
  }

  function tikrasBindTableSearch(root) {
    var scope = root || document;
    var inputs = scope.querySelectorAll(".tikras-data-search");
    for (var i = 0; i < inputs.length; i++) {
      var input = inputs[i];
      if (input.getAttribute("data-tikras-bound") === "1") {
        continue;
      }
      input.setAttribute("data-tikras-bound", "1");
      input.addEventListener("input", function () {
        var tableSelector = this.getAttribute("data-table") || "#dataTable";
        var table = document.querySelector(tableSelector);
        if (!table || !table.tBodies || !table.tBodies.length) {
          return;
        }

        var filter = tikrasText(this.value);
        var rows = table.tBodies[0].rows;
        var visible = 0;
        for (var r = 0; r < rows.length; r++) {
          var row = rows[r];
          var show = tikrasText(row.textContent).indexOf(filter) !== -1;
          row.style.display = show ? "" : "none";
          if (show) {
            visible++;
          }
        }

        var counterSelector = this.getAttribute("data-counter");
        if (counterSelector) {
          var counter = document.querySelector(counterSelector);
          if (counter) {
            counter.textContent = visible;
          }
        }
      });
    }
  }

  function tikrasFormField(form, name) {
    return form ? form.querySelector('[name="' + name + '"]') : null;
  }

  function tikrasFieldValue(form, name) {
    var field = tikrasFormField(form, name);
    return field ? (field.value || "").toString().trim() : "";
  }

  function tikrasSelectedText(field) {
    if (!field) {
      return "";
    }
    if (field.options && field.selectedIndex >= 0) {
      return (field.options[field.selectedIndex].text || field.value || "").toString().trim();
    }
    return (field.value || "").toString().trim();
  }

  function tikrasSelectedCount(field) {
    var total = 0;
    if (!field || !field.options) {
      return total;
    }
    for (var i = 0; i < field.options.length; i++) {
      if (field.options[i].selected) {
        total++;
      }
    }
    return total;
  }

  function tikrasSetPreviewValue(preview, key, value) {
    var node = preview.querySelector('[data-ticket-preview-field="' + key + '"]');
    if (node) {
      node.textContent = value || "-";
    }
  }

  function tikrasBindTicketPreview(root) {
    var scope = root || document;
    var forms = scope.querySelectorAll(".tikras-ticket-form");
    for (var i = 0; i < forms.length; i++) {
      var form = forms[i];
      if (form.getAttribute("data-tikras-ticket-bound") === "1") {
        continue;
      }

      var page = form.closest ? form.closest(".tikras-ticket-page") : document;
      var preview = page ? page.querySelector("[data-ticket-preview]") : document.querySelector("[data-ticket-preview]");
      if (!preview) {
        continue;
      }

      form.setAttribute("data-tikras-ticket-bound", "1");

      var updatePreview = function () {
        var qty = parseInt(tikrasFieldValue(form, "qty"), 10);
        var qtyText = isNaN(qty) ? "-" : String(qty);
        var userMode = tikrasSelectedText(tikrasFormField(form, "user"));
        var userLength = tikrasFieldValue(form, "userl");
        var profile = tikrasSelectedText(tikrasFormField(form, "profile"));
        var server = tikrasSelectedText(tikrasFormField(form, "server"));
        if (server === "all") {
          server = "Tous";
        }
        var prefix = tikrasFieldValue(form, "prefix");
        var charMode = tikrasSelectedText(tikrasFormField(form, "char"));
        var timeLimit = tikrasFieldValue(form, "timelimit");
        var dataLimit = tikrasFieldValue(form, "datalimit");
        var dataUnit = tikrasFieldValue(form, "mbgb") === "1073741824" ? "GB" : "MB";
        var roamingEngine = tikrasFieldValue(form, "roaming_engine");
        var roamingModeField = tikrasFormField(form, "roaming_mode");
        var roamingMode = tikrasSelectedText(roamingModeField);
        var roamingValue = tikrasFieldValue(form, "roaming_mode");
        if (roamingEngine === "radius") {
          roamingMode = "RADIUS central";
        } else if (roamingValue === "local") {
          roamingMode = "Routeur actuel seulement";
        } else if (roamingValue === "selected") {
          roamingMode = "Routeurs sélectionnés";
        } else if (roamingValue === "all") {
          roamingMode = "Tous les routeurs";
        }
        var roamingSessions = tikrasFormField(form, "roaming_sessions[]");
        var syncProfile = tikrasFormField(form, "roaming_sync_profile");
        var shareChannelField = tikrasFormField(form, "share_channel");
        var shareChannel = tikrasSelectedText(shareChannelField);
        var shareValue = tikrasFieldValue(form, "share_channel");
        var shareTarget = tikrasFieldValue(form, "share_target");
        var afterGenerate = tikrasFieldValue(form, "after_generate");
        var warnings = [];

        tikrasSetPreviewValue(preview, "qty", qtyText);
        tikrasSetPreviewValue(preview, "mode", userMode || "-");
        tikrasSetPreviewValue(preview, "profile", profile || "-");
        tikrasSetPreviewValue(preview, "server", server || "-");
        tikrasSetPreviewValue(preview, "code", (prefix ? prefix + " + " : "") + (userLength || "-") + " car. / " + (charMode || "-"));
        tikrasSetPreviewValue(preview, "limits", (timeLimit || "Temps illimité") + " / " + (dataLimit && dataLimit !== "0" ? dataLimit + " " + dataUnit : "Données illimitées"));
        tikrasSetPreviewValue(preview, "roaming", roamingMode + (roamingEngine === "radius" ? " + User Manager" : (syncProfile && syncProfile.checked ? " + profil distant" : "")));
        tikrasSetPreviewValue(preview, "share", shareChannel + (shareTarget ? " : " + shareTarget : " : sans destinataire"));

        if (isNaN(qty) || qty < 1) {
          warnings.push("La quantité doit être au moins 1.");
        } else if (qty > 3000) {
          warnings.push("La quantité dépasse la limite de 3000 tickets.");
        } else if (qty > 500) {
          warnings.push("Génération lourde : testez d'abord un petit lot si le routeur est lent.");
        }

        if (!profile) {
          warnings.push("Choisissez un profil avant de générer.");
        }

        if (prefix.length > 6) {
          warnings.push("Le préfixe doit rester sur 6 caractères maximum.");
        }

        if ((!timeLimit || timeLimit === "0") && (!dataLimit || dataLimit === "0")) {
          warnings.push("Aucune limite temps/données n'est définie pour ces tickets.");
        }

        if (roamingEngine !== "radius" && roamingValue === "selected" && tikrasSelectedCount(roamingSessions) < 1) {
          warnings.push("Mode routeurs sélectionnés actif : choisissez au moins un routeur distant.");
        }

        if (afterGenerate === "single" && qty > 1) {
          warnings.push("L'ouverture automatique du ticket ne s'applique qu'à un seul ticket.");
        }

        if (shareValue === "email" && shareTarget && shareTarget.indexOf("@") === -1) {
          warnings.push("L'adresse email semble incomplète.");
        }

        if (shareValue === "whatsapp" && shareTarget && !/^[0-9]+$/.test(shareTarget)) {
          warnings.push("Le numéro WhatsApp doit contenir seulement les chiffres avec l'indicatif.");
        }

        if ((shareValue === "email" || shareValue === "whatsapp") && !shareTarget) {
          warnings.push("Ajoutez un destinataire pour l'envoi automatique, ou gardez seulement le lien après génération.");
        }

        var list = preview.querySelector("[data-ticket-preview-warnings]");
        var note = preview.querySelector("[data-ticket-preview-note]");
        if (list) {
          list.innerHTML = "";
          for (var w = 0; w < warnings.length; w++) {
            var item = document.createElement("li");
            item.textContent = warnings[w];
            list.appendChild(item);
          }
        }
        if (note) {
          note.textContent = warnings.length ? "À vérifier avant génération." : "Prêt à générer.";
          note.className = "tikras-ticket-preview-note " + (warnings.length ? "is-warning" : "is-ready");
        }
      };

      form.addEventListener("input", updatePreview);
      form.addEventListener("change", updatePreview);
      updatePreview();
    }
  }

  function tikrasBoot(root) {
    tikrasBindTableSearch(root || document);
    tikrasBindTicketPreview(root || document);
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", function () {
      tikrasBoot(document);
    });
  } else {
    tikrasBoot(document);
  }

  if (window.jQuery) {
    window.jQuery(document).ajaxComplete(function () {
      tikrasBoot(document);
    });
  }
})();
