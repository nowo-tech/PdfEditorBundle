(function () {
  "use strict";

  var root = document.getElementById("nowo-pdf-editor-root");
  if (!root || root.getAttribute("data-pdf-editor-ready") === "1") {
    return;
  }
  root.setAttribute("data-pdf-editor-ready", "1");

  var inspectUrl = root.getAttribute("data-inspect-url");
  var pageUrlTemplate = root.getAttribute("data-page-url-template");
  var applyUrl = root.getAttribute("data-apply-url");
  var csrf = root.getAttribute("data-csrf-token");
  var thumbs = document.getElementById("nowo-pdf-editor-thumbs");
  var canvas = document.getElementById("nowo-pdf-editor-canvas");
  var toast = document.getElementById("nowo-pdf-editor-toast");
  var form = document.getElementById("nowo-pdf-editor-props-form");
  var empty = document.getElementById("nowo-pdf-editor-props-empty");
  var pendingList = document.getElementById("nowo-pdf-editor-pending");
  var pendingCount = document.getElementById("nowo-pdf-editor-pending-count");
  var commitBtn = root.querySelector('[data-action="commit"]');
  var discardBtn = root.querySelector('[data-action="discard"]');
  var textInput = form ? form.querySelector('[name$="[text]"]') : null;
  var currentPage = 1;
  var inspectData = null;
  var selected = null;
  var tool = "select";
  var pending = [];
  var renderGeneration = 0;
  var thumbsLoaded = false;
  var PAGE_PLACEHOLDER = "999999";

  function t(key, fallback) {
    return root.getAttribute("data-i18n-" + key) || fallback;
  }

  function showToast(message) {
    if (!toast) {
      return;
    }
    toast.hidden = false;
    toast.textContent = message;
    window.setTimeout(function () {
      toast.hidden = true;
    }, 2400);
  }

  function pageUrl(page) {
    return pageUrlTemplate.split(PAGE_PLACEHOLDER).join(String(page));
  }

  function pages() {
    return (inspectData && inspectData.pages) || [];
  }

  function pageInfo(page) {
    return pages()[page - 1] || { width: 1, height: 1, texts: [], fields: [] };
  }

  function opLabel(op) {
    var name = op.op || "op";
    if (op.page) {
      return name + " · p" + op.page;
    }
    return name;
  }

  function showCanvasStatus(message, isError) {
    if (!canvas) {
      return;
    }
    canvas.innerHTML = "";
    var status = document.createElement("p");
    status.className = "nowo-pdf-editor-status" + (isError ? " is-error" : "");
    status.textContent = message;
    canvas.appendChild(status);
  }

  function loadThumbImages() {
    if (!thumbs || thumbsLoaded) {
      return;
    }
    thumbsLoaded = true;
    Array.prototype.forEach.call(thumbs.querySelectorAll("img[data-src]"), function (img) {
      img.src = img.getAttribute("data-src");
      img.removeAttribute("data-src");
    });
  }

  function renderPending() {
    if (pendingCount) {
      pendingCount.textContent = String(pending.length);
    }
    if (commitBtn) {
      commitBtn.disabled = pending.length === 0;
    }
    if (discardBtn) {
      discardBtn.disabled = pending.length === 0;
    }
    if (!pendingList) {
      return;
    }
    pendingList.innerHTML = "";
    pending.forEach(function (op, index) {
      var item = document.createElement("li");
      item.textContent = opLabel(op);
      var remove = document.createElement("button");
      remove.type = "button";
      remove.className = "nowo-ui-btn nowo-ui-btn-tiny";
      remove.textContent = "×";
      remove.addEventListener("click", function () {
        pending.splice(index, 1);
        renderPending();
        renderPage(currentPage);
      });
      item.appendChild(remove);
      pendingList.appendChild(item);
    });
  }

  function queueOp(op) {
    pending.push(op);
    renderPending();
    renderPage(currentPage);
    showToast(t("queued", "Queued"));
  }

  function commitOps() {
    if (pending.length === 0) {
      return;
    }
    var ops = pending.slice();
    return fetch(applyUrl, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-CSRF-TOKEN": csrf || "",
      },
      body: JSON.stringify({ ops: ops }),
    }).then(function (response) {
      return response.json().then(function (body) {
        if (!response.ok || !body.ok) {
          throw new Error((body && body.error) || "Apply failed");
        }
        pending = [];
        renderPending();
        showToast(t("saved", "Saved to PDF"));
        thumbsLoaded = false;
        return loadInspect(true);
      });
    }).catch(function (error) {
      showToast(error.message);
    });
  }

  function discardOps() {
    pending = [];
    renderPending();
    renderPage(currentPage);
  }

  function drawGhosts(overlay, image, info) {
    var scaleX = image.clientWidth / info.width;
    var scaleY = image.clientHeight / info.height;
    pending.forEach(function (op) {
      if (op.page && op.page !== currentPage && op.op !== "add_watermark" && op.op !== "remove_detected_watermarks") {
        return;
      }
      var ghost = document.createElement("div");
      ghost.className = "nowo-pdf-editor-ghost";
      if (op.bbox) {
        ghost.style.left = op.bbox[0] * scaleX + "px";
        ghost.style.top = op.bbox[1] * scaleY + "px";
        ghost.style.width = (op.bbox[2] - op.bbox[0]) * scaleX + "px";
        ghost.style.height = (op.bbox[3] - op.bbox[1]) * scaleY + "px";
      } else if (typeof op.x === "number" && typeof op.y === "number") {
        ghost.style.left = op.x * scaleX + "px";
        ghost.style.top = (op.y - 14) * scaleY + "px";
        ghost.style.width = "120px";
        ghost.style.height = "18px";
      } else {
        ghost.style.left = "8%";
        ghost.style.top = "40%";
        ghost.style.width = "84%";
        ghost.style.height = "24px";
      }
      ghost.textContent = op.text || op.op;
      overlay.appendChild(ghost);
    });
  }

  function renderPage(page) {
    if (!inspectData) {
      return;
    }
    currentPage = page;
    var info = pageInfo(page);
    var generation = ++renderGeneration;
    canvas.innerHTML = "";
    var image = document.createElement("img");
    image.alt = "Page " + page;
    image.decoding = "async";
    image.src = pageUrl(page);
    var overlay = document.createElement("div");
    overlay.className = "nowo-pdf-editor-overlay";
    canvas.appendChild(image);
    canvas.appendChild(overlay);
    image.onload = function () {
      if (generation !== renderGeneration) {
        return;
      }
      var scaleX = image.clientWidth / info.width;
      var scaleY = image.clientHeight / info.height;
      (info.texts || []).forEach(function (span) {
        var box = document.createElement("button");
        box.type = "button";
        box.className = "nowo-pdf-editor-span";
        box.style.left = span.bbox[0] * scaleX + "px";
        box.style.top = span.bbox[1] * scaleY + "px";
        box.style.width = (span.bbox[2] - span.bbox[0]) * scaleX + "px";
        box.style.height = (span.bbox[3] - span.bbox[1]) * scaleY + "px";
        box.addEventListener("click", function (event) {
          event.stopPropagation();
          selected = span;
          if (form && empty && textInput) {
            form.hidden = false;
            empty.hidden = true;
            textInput.value = span.text;
          }
          overlay.querySelectorAll(".is-selected").forEach(function (el) {
            el.classList.remove("is-selected");
          });
          box.classList.add("is-selected");
        });
        overlay.appendChild(box);
      });
      drawGhosts(overlay, image, info);
      loadThumbImages();
    };
    image.onerror = function () {
      if (generation !== renderGeneration) {
        return;
      }
      showCanvasStatus(t("load-error", "Could not load page preview."), true);
    };
    Array.prototype.forEach.call(thumbs.querySelectorAll("img"), function (img) {
      img.classList.toggle("is-active", Number(img.getAttribute("data-page")) === page);
    });
  }

  function loadInspect(forceReload) {
    var hasPreview = !forceReload && canvas && canvas.querySelector("img");
    if (!hasPreview) {
      showCanvasStatus(t("loading", "Loading PDF…"), false);
    }
    return fetch(inspectUrl).then(function (response) {
      if (!response.ok) {
        throw new Error(t("load-error", "Could not load PDF."));
      }
      return response.json();
    }).then(function (data) {
      if (!data || !Array.isArray(data.pages) || data.pages.length === 0) {
        throw new Error(t("load-error", "Could not load PDF."));
      }
      inspectData = data;
      thumbsLoaded = false;
      thumbs.innerHTML = "";
      pages().forEach(function (page) {
        var img = document.createElement("img");
        img.alt = "Page " + page.number;
        img.setAttribute("data-src", pageUrl(page.number));
        img.setAttribute("data-page", String(page.number));
        img.addEventListener("click", function () {
          renderPage(page.number);
        });
        thumbs.appendChild(img);
      });
      renderPage(currentPage);
    }).catch(function (error) {
      showCanvasStatus(error.message || t("load-error", "Could not load PDF."), true);
      showToast(error.message || t("load-error", "Could not load PDF."));
    });
  }

  root.querySelectorAll("[data-tool]").forEach(function (button) {
    button.addEventListener("click", function () {
      tool = button.getAttribute("data-tool") || "select";
      root.querySelectorAll("[data-tool]").forEach(function (el) {
        el.classList.toggle("is-active", el === button);
      });
    });
  });

  root.querySelectorAll("[data-action]").forEach(function (button) {
    button.addEventListener("click", function () {
      var action = button.getAttribute("data-action");
      if (action === "remove-watermarks") {
        queueOp({ op: "remove_detected_watermarks" });
      }
      if (action === "rotate") {
        queueOp({ op: "rotate_page", page: currentPage, degrees: 90 });
      }
      if (action === "commit") {
        commitOps();
      }
      if (action === "discard") {
        discardOps();
      }
    });
  });

  if (form) {
    form.addEventListener("submit", function (event) {
      event.preventDefault();
      if (!selected || !textInput) {
        return;
      }
      queueOp({
        op: "replace_text",
        page: selected.page,
        bbox: selected.bbox,
        text: textInput.value,
        fontSize: selected.size || 11,
      });
    });
  }

  canvas.addEventListener("click", function (event) {
    if (tool === "select" || !inspectData) {
      return;
    }
    var info = pageInfo(currentPage);
    var image = canvas.querySelector("img");
    if (!image) {
      return;
    }
    var rect = image.getBoundingClientRect();
    var x = ((event.clientX - rect.left) / image.clientWidth) * info.width;
    var y = ((event.clientY - rect.top) / image.clientHeight) * info.height;
    if (tool === "text") {
      queueOp({ op: "insert_text", page: currentPage, x: x, y: y, text: "Text", fontSize: 12 });
    }
    if (tool === "form") {
      queueOp({
        op: "add_acroform_field",
        page: currentPage,
        name: "field_" + Date.now(),
        type: "text",
        bbox: [x, y, x + 140, y + 22],
      });
    }
    if (tool === "watermark") {
      queueOp({ op: "add_watermark", text: "CONFIDENTIAL", opacity: 0.18 });
    }
    if (tool === "annotate") {
      queueOp({ op: "add_annotation", page: currentPage, bbox: [x, y, x + 120, y + 36], text: "Note" });
    }
  });

  renderPending();
  loadInspect(false);
})();
