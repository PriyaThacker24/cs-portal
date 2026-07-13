"use strict";

$(function () {
    // ---- Search + category filter ----
    var vaultActiveCat = "all";

    function applyVaultFilter() {
        var term = ($("#vault-search").val() || "").toLowerCase().trim();
        var visible = 0;

        $(".vault-card").each(function () {
            var card = $(this);
            var matchesCat = vaultActiveCat === "all" || card.data("cat") === vaultActiveCat;
            var matchesTerm = term === "" || (card.data("search") + "").indexOf(term) !== -1;
            var show = matchesCat && matchesTerm;
            card.toggle(show);
            if (show) visible++;
        });

        $("#vault-no-results").toggleClass("tw-hidden", visible !== 0);
    }

    $(document).on("input", "#vault-search", applyVaultFilter);

    $(document).on("click", ".vault-filter", function () {
        $(".vault-filter").removeClass("active");
        $(this).addClass("active");
        vaultActiveCat = $(this).data("cat");
        applyVaultFilter();
    });

    // Open the "new entry" modal.
    $(document).on("click", "#vault-add-btn", function () {
        openEntryModal();
    });

    // Open the "edit entry" modal.
    $(document).on("click", ".vault-edit", function (e) {
        e.preventDefault();
        openEntryModal($(this).data("entry"));
    });

    function openEntryModal(entryId) {
        var url = admin_url + "project_vault/entry/" + vault_project_id + (entryId ? "/" + entryId : "");
        $.get(url, function (html) {
            $("#vault-entry-modal-content").html(html);
            applyCategoryFields();
            // The share dropdown is a bootstrap-select; initialize it manually
            // since this markup is injected after the page's global init ran.
            if ($.fn.selectpicker) {
                $("#vault-shared-with").selectpicker();
            }
            $("#vault-entry-modal").modal("show");
        });
    }

    // Show only the fields relevant to the selected category.
    function applyCategoryFields() {
        var category = $("#vault-category").val();
        $(".vault-cat-field").each(function () {
            var cats = ($(this).data("cat") + "").split(" ");
            $(this).toggle(cats.indexOf(category) !== -1);
        });
        // Notes is shown for every category but only required for Secure Note.
        $(".vault-req-notes").toggle(category === "secure_note");
    }

    // Re-toggle fields whenever the category changes.
    $(document).on("change", "#vault-category", applyCategoryFields);

    // Submit add/edit via AJAX (multipart for attachments).
    $(document).on("submit", "#vault-entry-form", function (e) {
        e.preventDefault();
        var form = this;
        var $form = $(form);

        // Category-specific required fields (mirrors server-side validation).
        var cat = $("#vault-category").val();
        var missing = null;

        if ($.trim($("#title").val()) === "") {
            missing = vault_lang.name_required;
        } else if (cat === "web_account" && $("#vault-password").val() === "" && $form.data("has-password") != 1) {
            missing = vault_lang.password_required;
        } else if (cat === "secure_note" && $.trim($("#notes").val()) === "") {
            missing = vault_lang.notes_required;
        } else if (cat === "file_storage") {
            var fileInput = $form.find('input[type="file"]')[0];
            var hasNew = fileInput && fileInput.files && fileInput.files.length > 0;
            if (!hasNew && $form.data("has-files") != 1) {
                missing = vault_lang.file_required;
            }
        }

        if (missing) {
            alert_float("danger", missing);
            return;
        }

        var data = new FormData(form);

        $.ajax({
            url: form.action,
            type: "POST",
            data: data,
            processData: false,
            contentType: false,
            dataType: "json",
        })
            .done(function (response) {
                if (response.success) {
                    alert_float("success", response.message);
                    $("#vault-entry-modal").modal("hide");
                    location.reload();
                } else {
                    alert_float("danger", response.message);
                }
            })
            .fail(function () {
                alert_float("danger", "Something went wrong");
            });
    });

    // Toggle the password field visibility in the form.
    $(document).on("click", ".vault-toggle-password", function () {
        var input = $("#vault-password");
        var icon = $(this).find("i");
        if (input.attr("type") === "password") {
            input.attr("type", "text");
            icon.removeClass("fa-eye").addClass("fa-eye-slash");
        } else {
            input.attr("type", "password");
            icon.removeClass("fa-eye-slash").addClass("fa-eye");
        }
    });

    // Clicking a reveal (eye) button: toggle the entry's secret + notes.
    $(document).on("click", ".vault-reveal", function (e) {
        e.preventDefault();
        var entryId = $(this).data("entry");
        var secretEl = $('.vault-secret-value[data-entry="' + entryId + '"]');
        var notesEl = $('.vault-notes-value[data-entry="' + entryId + '"]');

        // Toggle back to masked if already revealed.
        if (secretEl.data("revealed") || notesEl.data("revealed")) {
            maskEntry(entryId);
            return;
        }

        $.post(admin_url + "project_vault/reveal/" + vault_project_id + "/" + entryId, {}, function (response) {
            if (response.success) {
                revealEntry(entryId, response);
            }
        }, "json");
    });

    function revealEntry(entryId, data) {
        var secretEl = $('.vault-secret-value[data-entry="' + entryId + '"]');
        var notesEl = $('.vault-notes-value[data-entry="' + entryId + '"]');
        var eyeBtns = $('.vault-reveal[data-entry="' + entryId + '"]');

        if (secretEl.length) {
            secretEl.data("secret", data.secret);
            secretEl.text(data.secret === "" ? "—" : data.secret).data("revealed", true);
            $('.vault-copy-secret[data-entry="' + entryId + '"]').removeClass("tw-hidden");
        }
        if (notesEl.length) {
            // .text() keeps content escaped; CSS white-space:pre-wrap preserves
            // the original line breaks and spacing the user typed.
            notesEl.text(data.notes || "—").removeClass("text-muted").data("revealed", true);
        }
        eyeBtns.find("i").removeClass("fa-eye").addClass("fa-eye-slash");
    }

    function maskEntry(entryId) {
        var secretEl = $('.vault-secret-value[data-entry="' + entryId + '"]');
        var notesEl = $('.vault-notes-value[data-entry="' + entryId + '"]');
        var eyeBtns = $('.vault-reveal[data-entry="' + entryId + '"]');

        if (secretEl.length) {
            secretEl.text("••••••••").data("revealed", false).removeData("secret");
            $('.vault-copy-secret[data-entry="' + entryId + '"]').addClass("tw-hidden");
        }
        if (notesEl.length) {
            notesEl.html("<em>" + vault_hidden_label + "</em>").addClass("text-muted").data("revealed", false);
        }
        eyeBtns.find("i").removeClass("fa-eye-slash").addClass("fa-eye");
    }

    // Copy-to-clipboard for username / plain values.
    $(document).on("click", ".vault-copy", function (e) {
        e.preventDefault();
        copyText($(this).data("copy"));
    });

    // Copy a revealed secret.
    $(document).on("click", ".vault-copy-secret", function (e) {
        e.preventDefault();
        var secret = $('.vault-secret-value[data-entry="' + $(this).data("entry") + '"]').data("secret");
        if (secret !== undefined) {
            copyText(secret);
        }
    });

    function copyText(text) {
        navigator.clipboard.writeText(text).then(function () {
            alert_float("success", "Copied");
        });
    }

    // Delete an attachment from within the edit form.
    $(document).on("click", ".vault-delete-file", function (e) {
        e.preventDefault();
        if (!confirm_delete()) {
            return;
        }
        var el = $(this).closest("li");
        var fileId = $(this).data("file");
        $.post(admin_url + "project_vault/delete_file/" + vault_project_id + "/" + fileId, {}, function (response) {
            if (response.success) {
                el.remove();
            }
        }, "json");
    });

    // Whole-project activity log.
    $(document).on("click", "#vault-history-btn", function () {
        var url = admin_url + "project_vault/history/" + vault_project_id;
        $.get(url, function (html) {
            $("#vault-history-body").html(html);
            $("#vault-history-modal").modal("show");
        });
    });
});
