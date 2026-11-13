(() => {
  // agora/workspace.js
  var KEY = "agora-connection";
  var listeners = /* @__PURE__ */ new Set();
  var connections = [];
  var current = "";
  var profile = null;
  var reason = null;
  var remembered = () => {
    try {
      return localStorage.getItem(KEY) || "";
    } catch {
      return "";
    }
  };
  var remember = (id) => {
    try {
      id ? localStorage.setItem(KEY, id) : localStorage.removeItem(KEY);
    } catch {
    }
  };
  var workspace = {
    get me() {
      return profile;
    },
    /** Filtro de la bandeja que otra pantalla pide al abrirla (por ejemplo, «sin respuesta» desde el tablero). */
    pendingFilter: null,
    get connections() {
      return connections;
    },
    /** The account every screen must read. Empty while none is available. */
    current() {
      return current;
    },
    setProfile(value) {
      profile = value;
    },
    setConnections(items, why = null) {
      connections = Array.isArray(items) ? items : [];
      reason = why;
      const previous = current;
      const wanted = current || remembered();
      current = connections.some((c) => String(c.id) === String(wanted)) ? String(wanted) : connections[0] ? String(connections[0].id) : "";
      remember(current);
      if (current !== previous) notify();
      return current;
    },
    select(id) {
      if (profile?.user?.role !== "administrator") return false;
      const value = String(id ?? "");
      if (!connections.some((c) => String(c.id) === value) || value === current) return false;
      current = value;
      remember(value);
      notify();
      return true;
    },
    /** Lo que se dice cuando no hay subcuenta que mostrar: distinto para quien administra Ágora y para quien usa una subcuenta. */
    noAccountText(what) {
      if (profile?.user?.role === "administrator") return `Añade una cuenta desde Conexiones para ver ${what}.`;
      if (reason === "no_permission") return "Tu usuario no tiene activadas las conversaciones en su subcuenta. Pídele al administrador de la subcuenta que las active.";
      if (reason === "no_membership") return "Tu correo todavía no está asociado a ninguna subcuenta en Ágora. Pide al administrador que sincronice el equipo.";
      return "Tu usuario todavía no tiene acceso a ninguna subcuenta.";
    },
    /** Moneda de la subcuenta en uso: euros o dólares (dólares si no se sabe). */
    currency() {
      const found = connections.find((c) => String(c.id) === current);
      return found?.currency === "EUR" ? "EUR" : "USD";
    },
    name() {
      const found = connections.find((c) => String(c.id) === current);
      return found ? found.name : "";
    },
    onChange(listener) {
      listeners.add(listener);
      return () => listeners.delete(listener);
    },
    reset() {
      connections = [];
      current = "";
      profile = null;
      reason = null;
      remember("");
    }
  };
  function notify() {
    for (const listener of [...listeners]) listener(current);
  }
  function friendly(error) {
    if (!navigator.onLine) return "Sin conexión. Se reintentará al volver la red.";
    if (error instanceof TypeError || error instanceof RangeError || error instanceof ReferenceError || error instanceof SyntaxError)
      return "No se pudo mostrar esta información. Vuelve a entrar en un momento.";
    return error?.message || "No se pudo completar. Vuelve a intentarlo.";
  }

  // agora/skeleton.js
  var rows = (n) => Array.from({ length: n }, (_, i) => `<div class="sk-row" style="--i:${i}"><span class="sk sk-avatar"></span><div><span class="sk sk-line w60"></span><span class="sk sk-line w90"></span></div></div>`).join("");
  var bubbles = () => ["in w55", "out w40", "in w70", "out w50"].map((b, i) => `<span class="sk sk-bubble ${b}" style="--i:${i}"></span>`).join("");
  var tiles = (n) => Array.from({ length: n }, (_, i) => `<div class="sk-tile" style="--i:${i}"><span class="sk sk-line w40"></span><span class="sk sk-number"></span></div>`).join("");
  var cards = (n) => Array.from({ length: n }, (_, i) => `<div class="sk-card" style="--i:${i}"><span class="sk sk-line w60"></span><span class="sk sk-line w30"></span></div>`).join("");
  var lines = (n) => Array.from({ length: n }, (_, i) => `<span class="sk sk-line ${["w90", "w70", "w80", "w50"][i % 4]}" style="--i:${i}"></span>`).join("");
  var KINDS = { list: rows, chat: bubbles, tiles, cards, lines };
  var skeleton = (kind, count = 5) => `<div class="sk-wrap sk-${kind}" role="status" aria-busy="true" aria-label="Cargando">${(KINDS[kind] || lines)(count)}</div>`;

  // agora/format.js
  var grouped = (options = {}) => new Intl.NumberFormat("es-ES", { useGrouping: "always", ...options });
  var number = (value) => grouped().format(Number(value) || 0);
  var currencyCode = () => workspace.currency();
  function money(value, { decimals = 2 } = {}) {
    if (value === null || value === void 0 || value === "" || !Number.isFinite(Number(value))) return null;
    return symbol() + " " + grouped({ minimumFractionDigits: decimals, maximumFractionDigits: decimals }).format(Number(value));
  }
  var symbol = () => workspace.currency() === "EUR" ? "€" : "US$";

  // agora/won-alert.js
  var esc = (s) => String(s ?? "").replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" })[c]);
  var state = null;
  var timer = null;
  var popupPending = false;
  var dialog = null;
  var relaxed = false;
  var gateFn = null;
  var wonAlertState = () => state;
  async function api(path, method = "GET", body = null) {
    const r = await fetch((window.AGORA_API || "/api/") + path, { method, cache: "no-store", headers: { "Content-Type": "application/json", ...workspace.me?.csrf && method !== "GET" ? { "X-CSRF-Token": workspace.me.csrf } : {} }, ...body ? { body: JSON.stringify(body) } : {} });
    let data = {};
    try {
      data = await r.json();
    } catch {
    }
    if (!r.ok) throw Object.assign(new Error(data.error || "No se pudo completar."), { status: r.status, code: data.code });
    return data;
  }
  let alertEpoch = 0;
  function startWonAlert(gate = null) {
    gateFn = gate;
    popupPending = true;
    relaxed = false;
    schedule(4e3);
  }
  function schedule(ms) {
    clearTimeout(timer);
    timer = setTimeout(check, ms);
  }
  window.addEventListener("agora:opportunity-edited", () => schedule(1500));
  ["agora:logout", "agora:account-changing"].forEach(event => window.addEventListener(event, () => {
    alertEpoch++;
    clearTimeout(timer);
    state = null;
    paintChrome();
    if (dialog?.open) dialog.close();
  }));
  async function check() {
    const epoch = alertEpoch;
    const connection = workspace.current();
    const app = document.querySelector("#app");
    if (!connection || !app || app.hidden) return;
    try {
      const alert2 = await api(`workspace/won-alert?connectionId=${encodeURIComponent(connection)}`);
      if (epoch !== alertEpoch || connection !== workspace.current()) return;
      state = { ...alert2, connection, total: state && state.connection === connection && state.total ? state.total : alert2.count };
      paintChrome();
      if (alert2.count > 0 && dialog?.open) openDialog();
      else if (alert2.count > 0 && popupPending) {
        popupPending = false;
        const urgent = !!alert2.escalate;
        Promise.resolve(gateFn ? gateFn(urgent ? "tutorial" : "all") : null).then(() => {
          if (state && state.count > 0 && state.connection === workspace.current()) openDialog();
        });
      } else if (alert2.count === 0) {
        popupPending = false;
        if (dialog?.open) finish();
      }
    } catch {
    }
  }
  function paintChrome() {
    const count = state?.count || 0;
    document.querySelectorAll('button[data-view="board"]').forEach((b) => {
      b.classList.toggle("has-alert", count > 0);
      if (count > 0) b.dataset.count = String(count);
      else delete b.dataset.count;
    });
    const bar = document.querySelector("#won-banner");
    if (bar) {
      if (count > 0) {
        bar.hidden = false;
        bar.classList.toggle("urgent", !!state.escalate);
        bar.innerHTML = `<p><b>${count === 1 ? "Tienes 1 venta ganada sin monto" : `Tienes ${count} ventas ganadas sin monto`}.</b> Cada venta necesita su monto para contar en el tablero y en las comisiones.</p><button type="button" class="primary" id="won-banner-go">Completar ahora</button>`;
        bar.querySelector("#won-banner-go").onclick = () => openDialog();
      } else {
        bar.hidden = true;
        bar.innerHTML = "";
      }
    }
    window.dispatchEvent(new CustomEvent("agora:won-alert", { detail: { count } }));
  }
  function mustFinish() {
    return !!state?.escalate && !relaxed && state?.canEdit !== false;
  }
  var daysText = (d) => d === null || d === void 0 ? "" : d < 1 ? "hoy" : d === 1 ? "hace 1 día" : `hace ${d} días`;
  function openDialog() {
    if (!state || state.count < 1) return;
    if (dialog && !dialog.open) delete dialog.dataset.focused;
    if (!dialog) {
      dialog = document.createElement("dialog");
      dialog.id = "won-dialog";
      dialog.className = "won-dialog";
      document.body.append(dialog);
      dialog.addEventListener("cancel", (e) => {
        if (mustFinish()) e.preventDefault();
      });
      dialog.addEventListener("close", () => {
        if (mustFinish() && state?.count > 0) setTimeout(() => {
          if (!dialog.open) dialog.showModal();
        }, 0);
      });
      dialog.addEventListener("click", (e) => {
        if (e.target === dialog && !mustFinish()) dialog.close();
      });
    }
    render();
    if (!dialog.open) dialog.showModal();
  }
  var symbol2 = () => workspace.currency() === "EUR" ? "€" : "$";
  function render() {
    const urgent = mustFinish();
    const total = state.total ?? state.count;
    const done = Math.max(0, total - state.count);
    const rows2 = (state.items || []).map((item) => {
      const late = state.days > 0 && (item.days || 0) >= state.days;
      return `<form class="won-item${late ? " late" : ""}" data-won-form="${esc(item.id)}">
      <div class="won-item-info"><strong>${esc(item.name || "Venta ganada")}</strong>
        <small>${esc([item.contactName, item.assignedName].filter(Boolean).join(" · "))}</small>
        <span class="won-age${late ? " late" : ""}">${esc(daysText(item.days) || "")}</span></div>
      ${state.canEdit === false ? '<small class="muted won-nopermit">Pide a un administrador que complete el monto.</small>' : `<div class="won-field"><label class="sr-only" for="won-v-${esc(item.id)}">Monto</label><span class="won-currency" aria-hidden="true">${symbol2()}</span><input id="won-v-${esc(item.id)}" type="number" inputmode="decimal" min="0.01" step="0.01" placeholder="0,00" autocomplete="off"><button class="primary won-save">Guardar</button></div>`}
      <p class="won-item-status" role="status"></p></form>`;
    }).join("");
    const more = state.count > (state.items || []).length ? `<p class="won-more">Y ${state.count - state.items.length} más. Complétalas en Oportunidades → Ganadas.</p>` : "";
    dialog.innerHTML = `<div class="won-sheet${urgent ? " urgent" : ""}">
    <header class="won-head">
      <h2>${state.count === 1 ? "Una venta ganada sin monto" : `${state.count} ventas ganadas sin monto`}</h2>
      <p>${urgent ? `Llevas <b>${state.oldestDays} días</b> con ventas sin monto. Complétalas para seguir: sin el monto, la venta no cuenta en el tablero ni en tu comisión.` : "Escribe el monto de cada venta. Sin él, la venta no cuenta en el tablero ni en tu comisión."}</p>
      ${total > 1 ? `<div class="won-progress" role="img" aria-label="${done} de ${total} completadas"><i style="width:${Math.round(done / total * 100)}%"></i></div><small>${done} de ${total} completadas</small>` : ""}
    </header>
    <div class="won-items">${rows2}</div>${more}
    <footer class="won-actions"><button type="button" class="link-button" id="won-go">Ver todas en Ganadas</button>
      <span class="won-actions-end">${urgent ? "" : '<button type="button" class="secondary" id="won-later">Más tarde</button>'}<button type="button" class="primary" id="won-save-all" hidden></button></span></footer></div>`;
    dialog.querySelector("#won-go").onclick = () => {
      if (!mustFinish()) dialog.close();
      workspace.pendingOppTab = "won";
      window.dispatchEvent(new CustomEvent("agora:goto", { detail: { view: "board" } }));
    };
    const later = dialog.querySelector("#won-later");
    if (later) later.onclick = () => dialog.close();
    dialog.querySelectorAll("[data-won-form]").forEach((form) => {
      form.onsubmit = (e) => {
        e.preventDefault();
        save(form);
      };
      form.querySelector("input")?.addEventListener("input", syncAll);
    });
    const all = dialog.querySelector("#won-save-all");
    if (all) all.onclick = saveAll;
    syncAll();
    const first = dialog.querySelector(".won-item input");
    if (first && !dialog.dataset.focused) {
      dialog.dataset.focused = "1";
      setTimeout(() => first.focus(), 50);
    }
  }
  function filled() {
    return [...dialog.querySelectorAll("[data-won-form]")].filter((f) => Number(f.querySelector("input")?.value) > 0);
  }
  function syncAll() {
    const all = dialog?.querySelector("#won-save-all");
    if (!all) return;
    const n = filled().length;
    all.hidden = n < 2;
    all.textContent = `Guardar ${n} ventas`;
  }
  async function saveAll() {
    const all = dialog.querySelector("#won-save-all");
    all.disabled = true;
    for (const form of filled()) await save(form, true);
    if (dialog?.open && state.count > 0) render();
  }
  async function save(form, quiet = false) {
    const id = form.dataset.wonForm, input = form.querySelector("input"), button = form.querySelector(".won-save"), status = form.querySelector(".won-item-status");
    const value = Number(input.value);
    if (!(value > 0)) {
      status.textContent = "Escribe un monto mayor que cero.";
      input.focus();
      return;
    }
    button.disabled = true;
    status.textContent = "Guardando…";
    try {
      const connection = encodeURIComponent(state.connection);
      await api(`workspace/opportunity/edit?connectionId=${connection}&opportunityId=${encodeURIComponent(id)}`, "POST", { monetaryValue: value, requestId: crypto.randomUUID().replace(/-/g, "") });
      state.total ??= state.count;
      state.items = state.items.filter((i) => i.id !== id);
      state.count = Math.max(0, state.count - 1);
      state.oldestDays = Math.max(0, ...state.items.map((i) => i.days || 0));
      state.escalate = state.days > 0 && state.oldestDays >= state.days && state.count > 0;
      paintChrome();
      window.dispatchEvent(new Event("agora:opportunity-edited"));
      form.classList.add("saved");
      if (state.count === 0) finish();
      else if (!quiet) setTimeout(() => {
        if (dialog?.open) render();
      }, 350);
    } catch (error) {
      button.disabled = false;
      status.textContent = friendly(error);
      if (error.status === 403) {
        relaxed = true;
        state.canEdit = false;
        render();
      }
    }
  }
  function finish() {
    if (!dialog) return;
    dialog.innerHTML = `<div class="won-sheet"><header class="won-head won-done"><h2>¡Listo!</h2><p>Todas tus ventas ganadas tienen su monto.</p></header></div>`;
    setTimeout(() => {
      if (dialog?.open) dialog.close();
    }, 1400);
  }

  // agora/arp-extra.js
  var esc2 = (s) => String(s ?? "").replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" })[c]);
  var UI = () => window.AgoraUI || {};
  var nf = new Intl.NumberFormat("es-VE", { maximumFractionDigits: 0 });
  var num = (v) => v === null || v === void 0 || !isFinite(Number(v)) ? "—" : nf.format(Number(v));
  var dinero = (v) => UI().usd ? UI().usd(v) : v === null || v === void 0 ? "—" : "US$ " + nf.format(Number(v));
  var rueda = (items, opc) => UI().donut ? UI().donut(items, opc) : "";
  var barras = (items) => UI().barras ? UI().barras(items) : "";
  function contra(hoy, ayer, { alReves = false } = {}) {
    if (ayer === null || ayer === void 0 || !isFinite(Number(ayer)) || Number(ayer) === 0) return "";
    const d = (Number(hoy) - Number(ayer)) / Number(ayer) * 100;
    if (!isFinite(d)) return "";
    const sube = d > 0, bueno = alReves ? !sube : sube;
    return `<span class="delta ${bueno ? "up" : "down"}">${sube ? "↑" : "↓"} ${nf.format(Math.abs(Math.round(d)))} %</span><span class="delta-t">frente a ayer</span>`;
  }
  var sec = (t, s = "", a = "") => s || a ? `<div class="t-sec-row"><div><h2 class="t-sec">${t}</h2>${s ? `<p>${s}</p>` : ""}</div>${a}</div>` : `<h2 class="t-sec">${t}</h2>`;
  var panelx = (t, s, body, extra = "") => `<section class="panel"><div class="panel-h"><div><h2 class="t-panel">${t}</h2>${s ? `<p>${s}</p>` : ""}</div>${extra}</div>${body}</section>`;
  var tile = (l, v, f = "", cls = "") => `<div class="micro ${cls}"><h3>${esc2(l)}</h3><p class="t-metric">${v}</p>${f ? `<span class="f">${f}</span>` : ""}</div>`;
  var cargando = (n) => `<div class="grid g2">${'<div class="micro"><span class="sk w60"></span><span class="sk big"></span></div>'.repeat(n)}</div>`;
  var slotsDia = () => '<div id="arp-dia"></div><div id="arp-ads"></div>';
  var slotEficacia = () => '<div id="arp-efi"></div>';
  function pintarDia(nodo, d) {
    if (!d) {
      nodo.innerHTML = "";
      return;
    }
    const semaforo = d.slaIncumplido === 0 ? "ok" : d.slaIncumplido <= (d.slaTolerancia ?? 3) ? "warn" : "bad";
    const meta = d.slaMetaTexto || "5 min";
    const total = Math.max(1, d.conversacionesDia || d.slaIncumplido || 1);
    const enMeta = Math.round(100 * (1 - (d.slaIncumplido || 0) / total));
    nodo.innerHTML = `${sec("Operación del día", "Lo que está esperando respuesta ahora mismo y de dónde viene.")}
    <div class="grid g-hero">
      <section class="hero">
        <div>
          <p class="t-label">Tiempo de respuesta promedio (SLA)</p>
          <p class="t-hero" style="margin-top:10px">${esc2(d.slaPromedio || "—")}</p>
          <p class="f">${contra(d.slaPromedioSeg, d.slaPromedioSegAyer, { alReves: true }) || "Primer día con medición."} · La meta es responder en menos de ${esc2(meta)}.</p>
        </div>
        <div class="goal">
          <div class="pair"><span>Dentro de la meta</span><b class="${semaforo === "bad" ? "down" : semaforo === "ok" ? "up" : ""}">${enMeta} %</b></div>
          <div class="track" role="img" aria-label="Dentro de la meta: ${enMeta} %"><span class="fill ${semaforo}" style="width:${Math.max(3, enMeta)}%"></span></div>
          <p class="f">${d.slaIncumplido ? `${num(d.slaIncumplido)} de ${num(d.conversacionesDia)} pasaron de ${esc2(meta)} sin respuesta.` : "Hoy todas se respondieron dentro de la meta."}</p>
          <div class="lts"><span class="lt lt-ok"><i aria-hidden="true"></i>En meta ${num((d.conversacionesDia || 0) - (d.slaIncumplido || 0))}</span><span class="lt lt-bad"><i aria-hidden="true"></i>Fuera de meta ${num(d.slaIncumplido)}</span></div>
        </div>
      </section>
      <div class="grid g2">
        ${tile("Sin leer ahora", num(d.sinLeer), contra(d.sinLeer, d.sinLeerAyer, { alReves: true }) || "Conversaciones que nadie ha abierto")}
        ${tile("Leads del día", num(d.leads), contra(d.leads, d.leadsAyer))}
        ${tile("Primera respuesta más lenta", esc2(d.peorRespuesta || "—"), d.peorAsesor ? `De ${esc2(d.peorAsesor)}` : "Sin registro")}
        ${tile("Conversaciones atendidas", num(d.atendidas), `${num(d.conversacionesDia)} abiertas hoy`)}
      </div>
    </div>
    <div class="grid g3 mt">
      ${panelx("Sin leer por asesor", "Quién tiene conversaciones esperando.", rueda((d.sinLeerPorAsesor || []).map((x) => ({ l: x.nombre, n: x.n })), { rotulo: "Conversaciones sin leer por asesor" }))}
      ${panelx("Fuente de los leads", "Por dónde entraron hoy.", rueda((d.leadsPorFuente || []).map((x) => ({ l: x.fuente, n: x.n })), { rotulo: "Fuente de los leads" }))}
      ${panelx("Leads por ejecutivo", "A quién le tocaron.", rueda((d.leadsPorUsuario || []).map((x) => ({ l: x.nombre, n: x.n })), { rotulo: "Leads asignados por ejecutivo" }))}
    </div>`;
  }
  function pintarAds(nodo, a, periodoTexto) {
    if (!a) {
      nodo.innerHTML = "";
      return;
    }
    if (a.estado && a.estado !== "ok") {
      const porque = {
        expired: ["La conexión con Meta venció", "Hay que volver a autorizarla para seguir trayendo el gasto, las impresiones y las campañas."],
        not_connected: ["No hay cuenta publicitaria conectada", "Conecta la cuenta de Meta para ver aquí lo invertido cada día."],
        permission: ["Falta permiso para leer los anuncios", "La clave de la cuenta no alcanza a los anuncios. Pídelo a quien administra la cuenta publicitaria."]
      }[a.estado] || ["No se pudo leer la cuenta publicitaria", "Vuelve a intentarlo en un momento."];
      nodo.innerHTML = `${sec("Inversión en anuncios", "Lo que se está invirtiendo en Meta y qué devuelve.")}
      ${panelx(esc2(porque[0]), "", `<p class="empty"><b>${esc2(porque[0])}</b>${esc2(porque[1])}<br><a class="btn btn-sm btn-cta" href="/ajustes">Ir a Ajustes</a></p>`)}`;
      return;
    }
    const consumido = a.presupuestoMes ? Math.min(100, Math.round(Number(a.mes) / Number(a.presupuestoMes) * 100)) : null;
    const campanas = (a.campanas || []).slice(0, 6);
    nodo.innerHTML = `${sec("Inversión en anuncios", `Lo invertido en Meta y lo que devuelve. Actualizado ${esc2(a.actualizado || "hoy")}.`)}
    <div class="grid g-hero">
      <section class="hero">
        <div>
          <p class="t-label">Invertido en ${esc2(periodoTexto)}</p>
          <p class="t-hero" style="margin-top:10px">${esc2(dinero(a.mes))}</p>
          <p class="f">Hoy van ${esc2(dinero(a.dia))}${a.diaAyer !== void 0 ? ` · ayer ${esc2(dinero(a.diaAyer))}` : ""}.</p>
        </div>
        <div class="goal">
          ${a.presupuestoMes ? `<div class="pair"><span>Presupuesto del mes</span><b>${esc2(dinero(a.presupuestoMes))}</b></div>
          <div class="track" role="img" aria-label="Presupuesto consumido: ${consumido} %"><span class="fill ${consumido > 95 ? "bad" : consumido > 80 ? "warn" : ""}" style="width:${consumido}%"></span></div>
          <p class="f">Llevas ${consumido} % del presupuesto. Quedan ${esc2(dinero(Number(a.presupuestoMes) - Number(a.mes)))}.</p>` : '<p class="f">No hay presupuesto fijado para el mes. Se puede indicar en «Cifras del mes».</p>'}
        </div>
      </section>
      <div class="grid g2">
        ${tile("Impresiones", num(a.impresiones), a.clics !== void 0 ? `${num(a.clics)} clics` : "")}
        ${tile("Coste por conversión", esc2(dinero(a.costePorConversion)), `${num(a.conversiones)} conversiones`)}
        ${tile("Coste por lead", esc2(dinero(a.costePorLead)), "Inversión entre leads del día")}
        ${tile("Retorno por cada 100", esc2(dinero(a.retornoPorCien)), "Vendido entre invertido, por cien")}
      </div>
    </div>
    <div class="mt">${panelx(
      "Campañas por gasto",
      "Las que más consumen en el periodo.",
      barras(campanas.map((c) => ({ l: c.nombre, v: Number(c.gasto) || 0, texto: dinero(c.gasto) + (c.conversiones ? ` · ${num(c.conversiones)} conv.` : "") })))
    )}</div>`;
  }
  function pintarEficacia(nodo, e, { onFiltro } = {}) {
    if (!e) {
      nodo.innerHTML = "";
      return;
    }
    const sel = (id, rotulo, opciones, valor) => `<label class="fld">${rotulo}<select data-efi="${id}">${opciones.map((o) => `<option value="${esc2(o.id)}" ${String(o.id) === String(valor) ? "selected" : ""}>${esc2(o.nombre)}</option>`).join("")}</select></label>`;
    const metaDias = Number(e.metaDias) || 0;
    const dias = Number(e.duracionPromedioDias);
    const tono = !metaDias ? "" : dias <= metaDias ? "ok" : dias <= metaDias * 1.3 ? "warn" : "bad";
    nodo.innerHTML = `${sec(
      "Eficacia de ventas",
      "Cuánto tarda un cierre, cuánto deja y a qué ritmo entra.",
      `<div class="acts">${sel("pipeline", "Embudo", [{ id: "", nombre: "Todos los embudos" }, ...e.pipelines || []], e.pipelineId)}${sel("user", "Ejecutivo", [{ id: "", nombre: "Todo el equipo" }, ...e.usuarios || []], e.userId)}</div>`
    )}
    <div class="grid g-hero">
      <section class="hero">
        <div>
          <p class="t-label">Tiempo de cierre promedio</p>
          <p class="t-hero" style="margin-top:10px">${esc2(e.duracionTexto || "—")}</p>
          <p class="f">${metaDias ? `La meta es cerrar en ${num(metaDias)} días.` : "Sin meta de cierre fijada."} ${e.cerradas ? `Sobre ${num(e.cerradas)} ventas cerradas.` : ""}</p>
        </div>
        <div class="goal">
          ${metaDias ? `<div class="pair"><span>Frente a la meta</span><b class="${tono === "bad" ? "down" : tono === "ok" ? "up" : ""}">${dias <= metaDias ? "−" : "+"}${num(Math.abs(dias - metaDias))} días</b></div>
          <div class="track" role="img" aria-label="Tiempo de cierre frente a la meta"><span class="fill ${tono}" style="width:${Math.max(4, Math.min(100, Math.round(metaDias / Math.max(dias, 1) * 100)))}%"></span></div>` : ""}
          <p class="f">La velocidad dice cuánto valor entra al mes con el ritmo de hoy.</p>
        </div>
      </section>
      <div class="grid g2">
        ${tile("Valor cerrado", esc2(dinero(e.valorTotal)), `${num(e.cerradas)} ventas en el periodo`)}
        ${tile("Velocidad de venta", `${esc2(dinero(e.velocidad))}<small class="por">/mes</small>`, "Al ritmo actual de cierre")}
        ${tile("Ticket promedio", esc2(dinero(e.ticket)), "Valor entre ventas cerradas")}
        ${tile("Tasa de cierre", e.tasaCierre === null || e.tasaCierre === void 0 ? "—" : `${num(e.tasaCierre)} %`, "De las oportunidades trabajadas")}
      </div>
    </div>
    ${(e.porUsuario || []).length ? `<div class="mt">${panelx(
      "Por ejecutivo",
      "Quién cierra más rápido y cuánto deja.",
      `<div class="tw stack"><table><thead><tr><th scope="col">Ejecutivo</th><th scope="col" class="n">Cerradas</th><th scope="col" class="n">Valor</th><th scope="col" class="n">Días al cierre</th><th scope="col">Frente a la meta</th></tr></thead>
      <tbody>${e.porUsuario.map((u) => {
        const t = !metaDias ? "" : u.dias <= metaDias ? "ok" : u.dias <= metaDias * 1.3 ? "warn" : "bad";
        return `<tr><th scope="row">${esc2(u.nombre)}</th><td class="n" data-label="Cerradas">${num(u.cerradas)}</td><td class="n" data-label="Valor">${esc2(dinero(u.valor))}</td><td class="n" data-label="Días">${num(u.dias)}</td>
          <td data-label="Frente a la meta"><span class="cell-bar"><span class="track">${metaDias ? `<span class="fill ${t}" style="width:${Math.max(4, Math.min(100, Math.round(metaDias / Math.max(u.dias, 1) * 100)))}%"></span>` : ""}</span><b>${metaDias ? u.dias <= metaDias ? "En meta" : "+" + num(u.dias - metaDias) + " d" : "—"}</b></span></td></tr>`;
      }).join("")}</tbody></table></div>`
    )}</div>` : ""}`;
    nodo.querySelectorAll("[data-efi]").forEach((s) => {
      s.onchange = () => onFiltro?.({ pipelineId: nodo.querySelector('[data-efi="pipeline"]')?.value || "", userId: nodo.querySelector('[data-efi="user"]')?.value || "" });
    });
  }
  async function pintarExtras(container, { connection, period, request: request6, active }) {
    const dia = container.querySelector("#arp-dia");
    const ads = container.querySelector("#arp-ads");
    const efi = container.querySelector("#arp-efi");
    if (dia) dia.innerHTML = cargando(2);
    if (ads) ads.innerHTML = cargando(2);
    const hoy = (/* @__PURE__ */ new Date()).toISOString().slice(0, 10);
    const q = `connectionId=${encodeURIComponent(connection)}&period=${encodeURIComponent(period)}&date=${hoy}`;
    const periodoTexto = (window.AgoraUI?.mesNombre ? window.AgoraUI.mesNombre(period) : period).toLowerCase();
    const traer = async (ruta) => {
      try {
        return await request6(ruta);
      } catch {
        return null;
      }
    };
    const [d, a] = await Promise.all([traer(`workspace/dia?${q}`), traer(`workspace/ads?${q}`)]);
    if (!active()) return;
    if (dia) pintarDia(dia, d);
    if (ads) pintarAds(ads, a, periodoTexto);
    if (window.AgoraUI) {
      window.AgoraUI.guardarDia?.(d);
      window.AgoraUI.guardarAds?.(a);
    }
    if (!efi) return;
    let filtro = { pipelineId: "", userId: "" };
    const cargarEfi = async () => {
      efi.innerHTML = cargando(2);
      const e = await traer(`workspace/eficacia?${q}&pipelineId=${encodeURIComponent(filtro.pipelineId)}&userId=${encodeURIComponent(filtro.userId)}`);
      if (!active()) return;
      pintarEficacia(efi, e, { onFiltro: (f) => {
        filtro = f;
        cargarEfi();
      } });
      window.AgoraUI?.guardarEficacia?.(e);
    };
    await cargarEfi();
  }

  // agora/dashboard.js
  var money2 = (value) => money(value);
  var esc3 = (s) => String(s ?? "").replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" })[c]);
  var asList = (value) => Array.isArray(value) ? value : [];
  var MONTHS = ["enero", "febrero", "marzo", "abril", "mayo", "junio", "julio", "agosto", "septiembre", "octubre", "noviembre", "diciembre"];
  var pad2 = (n) => String(n).padStart(2, "0");
  var ymd = (d) => `${d.getFullYear()}-${pad2(d.getMonth() + 1)}-${pad2(d.getDate())}`;
  var addDays = (d, n) => {
    const x = new Date(d);
    x.setDate(x.getDate() + n);
    return x;
  };
  var PRESETS = [["today", "Hoy"], ["yesterday", "Ayer"], ["week", "7 días"], ["d30", "30 días"], ["month", "Este mes"], ["prev", "Mes anterior"], ["monthpick", "Otro mes"], ["custom", "Rango"]];
  function periodOf(sel, now = /* @__PURE__ */ new Date()) {
    const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
    const cur = ymd(today).slice(0, 7);
    const monthly = (y, m) => {
      const last = new Date(y, m + 1, 0);
      return { from: ymd(new Date(y, m, 1)), to: ymd(last < today ? last : today), month: `${y}-${pad2(m + 1)}` };
    };
    switch (sel?.kind) {
      case "yesterday": {
        const d = addDays(today, -1);
        return { from: ymd(d), to: ymd(d), month: cur, label: "ayer" };
      }
      case "week":
        return { from: ymd(addDays(today, -6)), to: ymd(today), month: cur, label: "últimos 7 días" };
      case "d30":
        return { from: ymd(addDays(today, -29)), to: ymd(today), month: cur, label: "últimos 30 días" };
      case "month":
        return { ...monthly(today.getFullYear(), today.getMonth()), label: "este mes" };
      case "prev": {
        const p = new Date(today.getFullYear(), today.getMonth() - 1, 1);
        return { ...monthly(p.getFullYear(), p.getMonth()), label: "mes anterior" };
      }
      case "monthpick": {
        const m = /^(\d{4})-(\d{2})$/.exec(sel.month || "");
        if (m) {
          const r = monthly(Number(m[1]), Number(m[2]) - 1);
          return { ...r, label: monthNameShort(r.month) };
        }
        return { ...monthly(today.getFullYear(), today.getMonth()), label: "este mes" };
      }
      case "custom": {
        const ok = (v) => /^\d{4}-\d{2}-\d{2}$/.test(v || "");
        if (ok(sel.from) && ok(sel.to) && sel.from <= sel.to) return { from: sel.from, to: sel.to, month: sel.to.slice(0, 7) > cur ? cur : sel.to.slice(0, 7), label: `${dayLabel(sel.from)} – ${dayLabel(sel.to)}` };
        return { from: ymd(today), to: ymd(today), month: cur, label: "hoy" };
      }
      default:
        return { from: ymd(today), to: ymd(today), month: cur, label: "hoy" };
    }
  }
  var dayLabel = (v) => {
    const [y, m, d] = v.split("-").map(Number);
    return `${d} ${MONTHS[m - 1].slice(0, 3)}${y !== (/* @__PURE__ */ new Date()).getFullYear() ? " " + y : ""}`;
  };
  var cap = (t) => t.charAt(0).toUpperCase() + t.slice(1);
  var monthOptions = (selected, count = 24) => {
    const now = /* @__PURE__ */ new Date(), seen = [];
    for (let i = 0; i < count; i++) {
      const d = new Date(now.getFullYear(), now.getMonth() - i, 1);
      seen.push(`${d.getFullYear()}-${pad2(d.getMonth() + 1)}`);
    }
    if (selected && /^\d{4}-\d{2}$/.test(selected) && !seen.includes(selected)) seen.push(selected);
    return seen.map((v) => {
      const [y, m] = v.split("-");
      return `<option value="${v}"${v === selected ? " selected" : ""}>${cap(MONTHS[Number(m) - 1])} ${y}</option>`;
    }).join("");
  };
  var monthNameShort = (p) => {
    const [y, m] = String(p).split("-");
    return MONTHS[Number(m) - 1] ? `${MONTHS[Number(m) - 1]} ${y}` : p;
  };
  var monthName = (period) => {
    const [y, m] = String(period || "").split("-");
    return MONTHS[Number(m) - 1] ? `${MONTHS[Number(m) - 1]} de ${y}` : period;
  };
  async function request(path, method = "GET", csrf2 = "", body = null) {
    const r = await fetch((window.AGORA_API || "/api/") + path, { method, cache: "no-store", ...body !== null ? { body: JSON.stringify(body) } : {}, headers: { "Content-Type": "application/json", ...csrf2 ? { "X-CSRF-Token": csrf2 } : {} } });
    let data;
    try {
      data = await r.json();
    } catch {
      throw Object.assign(Error(`El servidor devolvió una respuesta no válida (HTTP ${r.status}).`), { status: r.status });
    }
    if (!r.ok) throw Object.assign(Error((data.error || "Error de conexión") + (data.retryAfter ? ` Espera ${data.retryAfter} segundos.` : "")), { status: r.status, code: data.code });
    return data;
  }
  var compact = new Intl.NumberFormat("es-ES", { notation: "compact", maximumFractionDigits: 1 });
  var tip = (html) => ` data-tip="${esc3(html)}"`;
  var niceMax = (v) => {
    const p = 10 ** Math.floor(Math.log10(v || 1)), n = v / p;
    return (n <= 1 ? 1 : n <= 2 ? 2 : n <= 2.5 ? 2.5 : n <= 5 ? 5 : 10) * p;
  };
  function metric(label, value, note, cls = "") {
    return `<div class="micro ${cls}"><h3>${esc3(label)}</h3><p class="t-metric">${value === null || value === void 0 ? "—" : esc3(value)}</p>${note ? `<span class="f">${typeof note === "object" ? note.html : esc3(note)}</span>` : ""}</div>`;
  }
  var metricGrid = (items) => `<div class="grid g2">${items.map((html, i) => items.length % 2 === 1 && i === items.length - 1 ? html.replace('class="micro ', 'class="micro span2 ') : html).join("")}</div>`;
  var panel = (title, sub, body, extra = "") => `<section class="panel"><div class="panel-h"><div><h2 class="t-panel">${title}</h2>${sub ? `<p>${sub}</p>` : ""}</div>${extra}</div>${body}</section>`;
  var sec2 = (title, sub = "", acts = "") => sub || acts ? `<div class="t-sec-row"><div><h2 class="t-sec">${title}</h2>${sub ? `<p>${sub}</p>` : ""}</div>${acts}</div>` : `<h2 class="t-sec">${title}</h2>`;
  var empty = (title, text = "") => `<p class="empty"><b>${esc3(title)}</b>${esc3(text)}</p>`;
  function bars(items, { max } = {}) {
    const top = max ?? Math.max(1, ...items.map((i) => Math.max(i.v || 0, i.t || 0)));
    return `<div class="bars">${items.map((i) => `<div class="bar-row"${tip(i.tip || `<b>${esc3(i.l)}</b><br>${esc3(i.label)}`)}><span class="lab" title="${esc3(i.l)}">${esc3(i.l)}</span><span class="track"><span class="fill ${esc3(i.kind || "")}" style="width:${Math.min(100, 100 * (i.v || 0) / top)}%"></span>${i.t != null ? `<span class="target" style="left:${Math.min(100, 100 * i.t / top)}%"></span>` : ""}</span><span class="val">${esc3(i.label)}${i.small ? `<small>${esc3(i.small)}</small>` : ""}</span></div>`).join("")}</div>`;
  }
  function waterfall(p) {
    const sales = Number(p.sales) || 0;
    if (sales <= 0) return empty("Todavía no hay nada cerrado este mes.");
    const ads = p.ads === null || p.ads === void 0 ? null : Number(p.ads);
    const commissions = Number(p.commissions) || 0;
    const profit = p.profit === null || p.profit === void 0 ? null : Number(p.profit);
    const afterAds = ads === null ? sales : sales - ads;
    const afterCom = afterAds - commissions;
    const narrow = innerWidth < 760;
    const share = (v) => `${number(Math.round(Math.abs(v) / sales * 100))} % de las ventas`;
    const steps = [
      { l: "Ventas", a: 0, b: sales, color: "var(--c-deep)", text: money2(sales), tip: `<b>Ventas</b><br>${esc3(money2(sales))}` },
      { l: narrow ? "Publicidad" : "Publicidad y gastos", a: sales, b: afterAds, missing: ads === null, color: "var(--muted-2)", text: ads === null ? "" : "−" + money2(ads), tip: ads === null ? "<b>Publicidad y gastos</b><br>Falta el dato" : `<b>Publicidad y gastos</b><br>−${esc3(money2(ads))}<br>${share(ads)}` },
      { l: "Comisiones", a: afterAds, b: afterCom, color: "var(--muted-2)", text: "−" + money2(commissions), tip: `<b>Comisiones</b><br>−${esc3(money2(commissions))}<br>${share(commissions)}` },
      { l: "Ganancia", a: 0, b: profit ?? 0, missing: profit === null, color: (profit ?? 0) < 0 ? "var(--bad-solid)" : "var(--brand)", text: profit === null ? "" : (profit < 0 ? "−" : "") + money2(Math.abs(profit)), tip: profit === null ? "<b>Ganancia</b><br>Falta el dato" : `<b>Ganancia</b><br>${profit < 0 ? "−" : ""}${esc3(money2(Math.abs(profit)))}${profit > 0 ? "<br>" + share(profit) : ""}` }
    ];
    const ends = steps.flatMap((s) => s.missing ? [0] : [s.a, s.b]);
    const hi = niceMax(Math.max(sales, ...ends)), lowest = Math.min(0, ...ends), lo = lowest < 0 ? -niceMax(-lowest) : 0;
    const W = narrow ? 400 : 600, H = 260, L = narrow ? 44 : 54, R = 12, T = 30, B = 34, w = W - L - R, ih = H - T - B;
    const y = (v) => T + ih - (v - lo) / (hi - lo) * ih, x = (i) => L + (i + 0.5) * w / steps.length, bw = Math.min(66, w / steps.length * 0.56);
    let g = "";
    for (let k = 0; k <= 4; k++) {
      const v = lo + (hi - lo) * k / 4;
      g += `<line class="gl" x1="${L}" x2="${W - R}" y1="${y(v)}" y2="${y(v)}"/><text x="${L - 8}" y="${y(v) + 4}" text-anchor="end">${esc3(compact.format(v))}</text>`;
    }
    if (lo < 0) g += `<line class="zero" x1="${L}" x2="${W - R}" y1="${y(0)}" y2="${y(0)}"/>`;
    steps.forEach((s, i) => {
      const cx = x(i);
      if (s.missing) {
        g += `<rect class="miss" x="${cx - bw / 2}" y="${y(0) - 34}" width="${bw}" height="34" rx="6"/><text class="dl" x="${cx}" y="${y(0) - 42}" text-anchor="middle">Falta el dato</text>`;
      } else {
        const top = y(Math.max(s.a, s.b)), height = Math.max(3, Math.abs(y(s.a) - y(s.b)));
        g += `<rect x="${cx - bw / 2}" y="${top}" width="${bw}" height="${height}" rx="6" fill="${s.color}"/><text class="dl" x="${cx}" y="${top - 7}" text-anchor="middle">${esc3(s.text)}</text>`;
        if (i < steps.length - 1) g += `<line class="link" x1="${cx + bw / 2}" x2="${x(i + 1) - bw / 2}" y1="${y(s.b)}" y2="${y(s.b)}"/>`;
      }
      g += `<text x="${cx}" y="${H - 10}" text-anchor="middle">${esc3(s.l)}</text>`;
      g += `<rect class="hit" x="${cx - w / steps.length / 2}" y="${T}" width="${w / steps.length}" height="${ih}"${tip(s.tip)}/>`;
    });
    const summary = `Ventas ${money2(sales)}${ads === null ? "" : ", publicidad y gastos " + money2(ads)}, comisiones ${money2(commissions)}${profit === null ? "" : ", ganancia " + money2(profit)}`;
    return `<svg class="chart" viewBox="0 0 ${W} ${H}" role="img" aria-label="De las ventas del mes a la ganancia. ${esc3(summary)}">${g}</svg>`;
  }
  function commissionText(rule, word = "cierres") {
    if (!rule) return "10 %";
    const one = String(word).replace(/s$/, "");
    return rule.kind === "per_closing" ? `${money2(rule.amount)} por ${one}` : `${number(rule.amount)} %`;
  }
  function origin(month, word) {
    const one = String(word).replace(/s$/, "");
    const rule = month.commission || { kind: "percent", amount: 10 };
    const commission = rule.kind === "per_closing" ? `el monto fijo por ${one} (${money2(rule.amount)}) por el número de ${word}` : `el ${number(rule.amount)} % de las ventas`;
    const row = (name2, text) => `<li><b>${esc3(name2)}</b>${esc3(text)}</li>`;
    return `<details class="origin"><summary>¿De dónde salen estas cifras?</summary><ul>
    ${row("Ventas y " + word + ": ", "lo ganado en el mes en tu cuenta, más lo que se marca como «¿Venta ganada?» desde Ágora. Cada venta cuenta en el mes en que se cierra.")}
    ${row("Leads: ", "los que la cuenta le asignó a cada quien dentro del mes. Un lead cuenta en el mes en que se asignó, no en el que entró.")}
    ${row("Conversión: ", "los " + word + " de cada quien entre sus leads asignados del mes, por cien. El total del equipo usa la suma de los dos, no el promedio de los porcentajes.")}
    ${row("Publicidad: ", "lo gastado en la cuenta publicitaria de Meta conectada durante el mes, más lo que escribas en «Cifras del mes» como otros gastos.")}
    ${row("Comisiones: ", "calculadas con " + commission + ". La regla la fija el administrador en Conexiones y vale desde el mes en que se cambia.")}
    ${row("Ganancia: ", "ventas menos publicidad y comisiones.")}
    ${row("Ganancia real del mes: ", "la ganancia menos los costos fijos que escribes en «Cifras del mes».")}
    ${row("Costo por " + one + ": ", "publicidad entre el número de " + word + ".")}
    ${row("Ventas por cada 100 invertido: ", "ventas entre publicidad, por cien.")}
    ${row("Vendedores: ", "el equipo de tu cuenta. Quienes administran no aparecen aquí, pero lo que cierran sí cuenta en el total.")}
    ${row("Metas y costos fijos: ", "los escribes tú en «Cifras del mes»; se guardan por subcuenta y por mes.")}
  </ul></details>`;
  }
  var STATES = { lograda: ["Meta lograda", "ok"], cerca: ["Cerca de la meta", "warn"], apoyo: ["Necesita apoyo", "bad"], "sin-meta": ["Sin meta fijada", "none"] };
  var lamp = (state2) => {
    const [label, kind] = STATES[state2] || STATES["sin-meta"];
    return `<span class="lt lt-${kind}"><i aria-hidden="true"></i>${esc3(label)}</span>`;
  };
  function teamTable(data) {
    const rows2 = asList(data.team);
    if (!rows2.length) return empty("Todavía no hay equipo importado en esta cuenta.");
    const word = data.settings?.closingWord || "cierres";
    const heading = word[0].toUpperCase() + word.slice(1);
    const cell = (value) => value === null || value === void 0 ? "—" : esc3(money2(value));
    const diff = (value) => value === null || value === void 0 ? "—" : `<span class="${Number(value) < 0 ? "down" : "up"}">${Number(value) >= 0 ? "+" : "−"}${esc3(money2(Math.abs(value)))}</span>`;
    const kindOf = (progress) => progress === null || progress === void 0 ? "" : progress >= data.settings.green ? "ok" : progress >= data.settings.amber ? "warn" : "bad";
    const leadsDe = (row) => row.leads === null || row.leads === void 0 ? null : Number(row.leads);
    const tasa = (cierres, leads) => leads ? Math.round((Number(cierres) || 0) / leads * 1e3) / 10 : null;
    const tasaCell = (v) => v === null ? '<span class="muted">—</span>' : `${number(v)} %`;
    const leadsEquipo = rows2.reduce((suma, row) => leadsDe(row) === null ? suma : suma + leadsDe(row), 0);
    const body = rows2.map((row) => {
      const width = Math.max(0, Math.min(100, Number(row.progress) || 0));
      return `<tr><th scope="row">${esc3(row.name)}</th><td class="n" data-label="Leads">${leadsDe(row) === null ? '<span class="muted">—</span>' : number(leadsDe(row))}</td><td class="n" data-label="${esc3(heading)}">${number(row.closings)}</td><td class="n" data-label="Conversión">${tasaCell(tasa(row.closings, leadsDe(row)))}</td><td class="n" data-label="Meta">${cell(row.goal)}</td><td class="n" data-label="Vendido">${cell(row.sold)}</td>
      <td class="n" data-label="Diferencia">${diff(row.difference)}</td>
      <td data-label="Avance"><span class="cell-bar"><span class="track">${row.progress === null || row.progress === void 0 ? "" : `<span class="fill ${kindOf(row.progress)}" style="width:${width}%"></span>`}</span><b>${row.progress === null ? "—" : number(Math.round(row.progress)) + " %"}</b></span></td>
      <td data-label="Estado">${lamp(row.state)}</td></tr>`;
    }).join("");
    const goal = data.goal || {}, sales = data.sales || {};
    const total = goal.target !== null && goal.target !== void 0 && sales.total !== null && sales.total !== void 0 ? `<tfoot><tr><td>Total del equipo</td><td class="n" data-label="Leads">${leadsEquipo ? number(leadsEquipo) : "—"}</td><td class="n" data-label="${esc3(heading)}">${number(sales.closings ?? 0)}</td><td class="n" data-label="Conversión"><b>${tasaCell(tasa(sales.closings, leadsEquipo))}</b></td><td class="n" data-label="Meta">${cell(goal.target)}</td><td class="n" data-label="Vendido">${cell(sales.total)}</td><td class="n" data-label="Diferencia">${diff(Number(sales.total) - Number(goal.target))}</td><td data-label="Avance">${goal.progress === null || goal.progress === void 0 ? "—" : `<b>${number(Math.round(goal.progress))} %</b>`}</td><td></td></tr></tfoot>` : "";
    return `<div class="tw stack"><table><thead><tr>
    <th scope="col">Asesora</th><th scope="col" class="n" title="Leads que se le asignaron en el mes">Leads</th><th scope="col" class="n">${esc3(heading)}</th><th scope="col" class="n" title="Cierres entre leads asignados">Conversión</th><th scope="col" class="n">Meta</th><th scope="col" class="n">Vendido</th><th scope="col" class="n">Diferencia</th><th scope="col">Avance</th><th scope="col">Estado</th>
  </tr></thead><tbody>${body}</tbody>${total}</table></div>`;
  }
  async function renderDashboard(container) {
    const instance = /* @__PURE__ */ Symbol();
    container.agoraViewInstance = instance;
    const active = () => container.agoraViewInstance === instance && container.dataset.view === "dashboard";
    const connection = workspace.current();
    if (!connection) {
      container.innerHTML = `<div class="tv3"><p class="empty">${esc3(workspace.noAccountText("el tablero"))}</p></div>`;
      return;
    }
    let sel = { kind: "today" };
    try {
      const saved = JSON.parse(localStorage.getItem("agora-board-period") || "null");
      if (saved && PRESETS.some((p) => p[0] === saved.kind)) sel = saved;
    } catch {
    }
    container.innerHTML = `<div class="tv3 enter">
    <header class="mod-h"><div><p class="t-label">Tablero</p><h1 class="t-module">Así va tu cuenta.</h1><p id="dashboard-feedback" class="lede" role="status" hidden></p></div><div id="period-bar" class="acts"></div></header>
    <div id="dashboard-body">${skeleton("tiles", 4)}</div></div>`;
    const $ = (s) => container.querySelector(s);
    let csrf2 = "", month = null, period = periodOf(sel).month, range = periodOf(sel);
    const today = ymd(/* @__PURE__ */ new Date());
    const CHEVRON = '<svg class="ic" viewBox="0 0 24 24" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>';
    const QUICK = [["today", "Hoy"], ["yesterday", "Ayer"], ["week", "7 días"], ["d30", "30 días"], ["month", "Este mes"], ["prev", "Mes anterior"]];
    const nowLabel = () => {
      const r = periodOf(sel);
      if (sel.kind === "today") return `Hoy · ${dayLabel(r.from)}`;
      if (sel.kind === "yesterday") return `Ayer · ${dayLabel(r.from)}`;
      if (sel.kind === "week") return "Últimos 7 días";
      if (sel.kind === "d30") return "Últimos 30 días";
      if (sel.kind === "month" || sel.kind === "prev" || sel.kind === "monthpick") return r.month ? monthName(r.month) : r.label;
      return r.label;
    };
    const tipEl = document.createElement("div");
    tipEl.className = "tv3-tip";
    tipEl.setAttribute("role", "tooltip");
    document.body.appendChild(tipEl);
    let tipTarget = null, tipTimer = 0;
    const hideTip = () => {
      tipTarget = null;
      tipEl.classList.remove("on");
    };
    const moveTip = (e) => {
      const node = e.target.closest?.("[data-tip]");
      if (!node || !container.contains(node)) {
        hideTip();
        return;
      }
      if (node !== tipTarget) {
        tipTarget = node;
        tipEl.innerHTML = node.dataset.tip;
      }
      tipEl.classList.add("on");
      const w = tipEl.offsetWidth, h = tipEl.offsetHeight;
      let left = e.clientX + 14, top = e.clientY + 16;
      if (left + w > innerWidth - 8) left = e.clientX - w - 14;
      if (top + h > innerHeight - 8) top = e.clientY - h - 14;
      tipEl.style.left = Math.max(8, left) + "px";
      tipEl.style.top = Math.max(8, top) + "px";
    };
    const touchTip = (e) => {
      if (e.pointerType === "mouse") return;
      moveTip(e);
      clearTimeout(tipTimer);
      tipTimer = setTimeout(hideTip, 2600);
    };
    container.addEventListener("pointermove", (e) => {
      if (e.pointerType === "mouse") moveTip(e);
    });
    container.addEventListener("pointerleave", hideTip);
    container.addEventListener("pointerdown", touchTip);
    window.addEventListener("scroll", hideTip, true);
    function placeThumbs(animate = false) {
      container.querySelectorAll(".seg").forEach((seg) => {
        const on = seg.querySelector(".seg-b.is-on"), thumb = seg.querySelector(".seg-thumb");
        if (!thumb) return;
        if (!on || !on.offsetWidth) {
          thumb.style.opacity = 0;
          return;
        }
        if (!animate) thumb.style.transition = "none";
        thumb.style.opacity = 1;
        thumb.style.width = on.offsetWidth + "px";
        thumb.style.transform = `translateX(${on.offsetLeft}px)`;
        if (!animate) {
          void thumb.offsetWidth;
          thumb.style.transition = "";
        }
      });
    }
    const onResize = () => placeThumbs(false);
    window.addEventListener("resize", onResize);
    const unlinkedNote = () => {
      const isOwner = workspace.me?.user?.role === "administrator" && workspace.me?.user?.identity_type === "native";
      return isOwner ? { html: '<button type="button" class="lnk" data-self>Elegir quién soy</button>' } : "Usa en Ágora el mismo correo que en la cuenta";
    };
    let restricted = false;
    let menuOpen = false;
    const closeMenu = () => {
      if (!menuOpen) return;
      menuOpen = false;
      const m = $("#period-menu"), b = $("#period-btn");
      if (m) {
        m.hidden = true;
        b?.setAttribute("aria-expanded", "false");
      }
    };
    function paintBar(animate = false) {
      const bar = $("#period-bar");
      const before = bar.querySelector(".seg-thumb");
      const from = before && before.style.opacity === "1" ? { width: before.style.width, transform: before.style.transform } : null;
      const other = ["monthpick", "custom"].includes(sel.kind);
      bar.innerHTML = `<div class="period">
      <div class="seg" role="radiogroup" aria-label="Periodo"><span class="seg-thumb"></span>${QUICK.map(([k, l]) => `<button type="button" role="radio" aria-checked="${sel.kind === k}" class="seg-b${sel.kind === k ? " is-on" : ""}" data-period="${k}">${l}</button>`).join("")}</div>
      <button type="button" id="period-btn" class="btn${other ? " btn-tint" : ""}" aria-haspopup="true" aria-expanded="${menuOpen}">${other ? esc3(sel.kind === "monthpick" ? cap(monthNameShort(periodOf(sel).month)) : periodOf(sel).label) : "Otro periodo"}${CHEVRON}</button>
      <div id="period-menu" class="pop" role="dialog" aria-label="Otro periodo"${menuOpen ? "" : " hidden"}>
        <label class="fld">Otro mes<select id="period-month"><option value="">Elegir mes…</option>${monthOptions(sel.kind === "monthpick" ? sel.month : "")}</select></label>
        <div class="rng"><label class="fld">Desde<input type="date" id="period-from" value="${esc3(sel.kind === "custom" ? sel.from : "")}" max="${today}"></label><label class="fld">Hasta<input type="date" id="period-to" value="${esc3(sel.kind === "custom" ? sel.to : "")}" max="${today}"></label></div>
        <button type="button" id="period-apply" class="btn btn-cta" disabled>Aplicar rango</button>
      </div></div>`;
      $("#period-btn").onclick = (e) => {
        e.stopPropagation();
        menuOpen = !menuOpen;
        $("#period-menu").hidden = !menuOpen;
        $("#period-btn").setAttribute("aria-expanded", String(menuOpen));
      };
      bar.querySelectorAll("[data-period]").forEach((b) => {
        b.onclick = () => {
          menuOpen = false;
          choose({ kind: b.dataset.period });
        };
        b.onkeydown = (e) => {
          if (e.key !== "ArrowRight" && e.key !== "ArrowLeft") return;
          const all = [...bar.querySelectorAll("[data-period]")], next = all[(all.indexOf(b) + (e.key === "ArrowRight" ? 1 : -1) + all.length) % all.length];
          e.preventDefault();
          next.focus();
          next.click();
        };
      });
      const m = $("#period-month"), f = $("#period-from"), t = $("#period-to"), apply = $("#period-apply");
      m.onchange = () => {
        if (/^\d{4}-\d{2}$/.test(m.value)) {
          menuOpen = false;
          choose({ kind: "monthpick", month: m.value });
        }
      };
      const ready = () => {
        apply.disabled = !(f.value && t.value && f.value <= t.value);
      };
      const clearPresets = () => bar.querySelectorAll(".seg-b").forEach((b) => {
        b.classList.remove("is-on");
        b.setAttribute("aria-checked", "false");
        placeThumbs(true);
      });
      m.oninput = () => {
        f.value = "";
        t.value = "";
        ready();
        clearPresets();
      };
      f.oninput = t.oninput = () => {
        m.value = "";
        ready();
        clearPresets();
      };
      f.onchange = t.onchange = ready;
      ready();
      apply.onclick = () => {
        if (!apply.disabled) {
          menuOpen = false;
          choose({ kind: "custom", from: f.value, to: t.value });
        }
      };
      const thumb = bar.querySelector(".seg-thumb");
      if (from && animate && thumb) {
        thumb.style.transition = "none";
        thumb.style.opacity = 1;
        thumb.style.width = from.width;
        thumb.style.transform = from.transform;
        void thumb.offsetWidth;
        thumb.style.transition = "";
      }
      requestAnimationFrame(() => placeThumbs(animate && !!from));
    }
    const outside = (e) => {
      if (menuOpen && !e.target.closest(".period")) closeMenu();
    };
    const escape = (e) => {
      if (e.key === "Escape" && menuOpen) {
        closeMenu();
        $("#period-btn")?.focus();
      }
    };
    document.addEventListener("click", outside);
    document.addEventListener("keydown", escape);
    function choose(next) {
      const before = sel.kind;
      sel = next;
      try {
        localStorage.setItem("agora-board-period", JSON.stringify(sel));
      } catch {
      }
      range = periodOf(sel);
      period = range.month;
      paintBar(QUICK.some((q) => q[0] === before) && QUICK.some((q) => q[0] === next.kind));
      if (sel.kind === "monthpick" && !sel.month || sel.kind === "custom" && !(sel.from && sel.to)) return;
      $("#dashboard-body").innerHTML = skeleton("tiles", 4);
      load();
    }
    window.addEventListener("agora:won-alert", onWon);
    container.agoraLiveDispose = () => {
      container.agoraViewInstance = null;
      document.removeEventListener("click", outside);
      document.removeEventListener("keydown", escape);
      window.removeEventListener("resize", onResize);
      window.removeEventListener("scroll", hideTip, true);
      window.removeEventListener("agora:won-alert", onWon);
      clearTimeout(tipTimer);
      tipEl.remove();
    };
    paintBar();
    try {
      csrf2 = (await request("me")).csrf || "";
    } catch {
    }
    async function load() {
      if (!$("#dashboard-body").children.length || $("#dashboard-body .sk-wrap")) $("#dashboard-body").innerHTML = skeleton("tiles", 4);
      try {
        const zone = (() => {
          try {
            return Intl.DateTimeFormat().resolvedOptions().timeZone || "";
          } catch {
            return "";
          }
        })();
        const now = await request(`workspace/dashboard?connectionId=${encodeURIComponent(connection)}${zone ? "&tz=" + encodeURIComponent(zone) : ""}&from=${range.from}&to=${range.to}`);
        if (!active()) return;
        paint(now, null);
        try {
          month = await request(`workspace/board-month?connectionId=${encodeURIComponent(connection)}&period=${period}`);
        } catch (error) {
          month = { error: friendly(error) };
        }
        if (!active()) return;
        paint(now, month);
        pintarExtras(container, { connection, period, request, active });
      } catch (error) {
        if (!active()) return;
        const feedback = $("#dashboard-feedback");
        feedback.hidden = false;
        feedback.textContent = friendly(error);
        $("#dashboard-body").innerHTML = "";
        if (error.status === 401) window.dispatchEvent(new Event("agora:logout"));
      }
    }
    function paint(data, month2) {
      const read = data.readAt ? new Date(data.readAt).toLocaleTimeString("es-ES", { hour: "2-digit", minute: "2-digit" }) : "";
      const feedback = $("#dashboard-feedback");
      feedback.hidden = false;
      feedback.textContent = `${data.restricted ? "Tus cifras" : "Cifras de la cuenta"} · ${nowLabel()}${read ? ` · actualizado a las ${read}` : ""}`;
      const unread = data.conversations?.unread ?? 0;
      const unlinked = unlinkedNote();
      restricted = data.restricted === true;
      const leads = data.leads || null;
      const noData = "La cuenta no devolvió este dato";
      const leadsValue = leads ? leads.today === null || leads.today === void 0 ? null : number(leads.today) : null;
      const heroLabel = leads ? sel.kind === "today" ? "Leads de hoy" : sel.kind === "yesterday" ? "Leads de ayer" : "Leads nuevos" : "Conversaciones";
      const heroValue = leads ? leadsValue : number(data.conversations?.total ?? 0);
      const heroNote = leads ? leadsValue === null ? noData : nowLabel() : "En esta cuenta";
      const mineValue = leads ? leads.mine === null || leads.mine === void 0 ? null : number(leads.mine) : number(unread);
      const heroPair = leads ? `<div class="pair"><span>Leads asignados a mí</span><b>${mineValue === null ? "—" : esc3(mineValue)}</b></div><p class="f">${leads.linked ? mineValue === null ? esc3(noData) : "Los que tienes a tu nombre en la cuenta" : typeof unlinked === "object" ? unlinked.html : esc3(unlinked)}</p>` : `<div class="pair"><span>Sin leer</span><b>${esc3(mineValue)}</b></div>`;
      const tiles2 = [
        ...leads ? [metric("Conversaciones", number(data.conversations?.total ?? 0))] : [],
        ...leads ? [metric("Sin leer", number(unread))] : [],
        ...data.conversations?.waiting !== void 0 ? [metric("Esperando respuesta", number(data.conversations.waiting), { html: '<button type="button" class="lnk" data-go-waiting>Ver la lista</button>' })] : [],
        ...data.contacts !== null && data.contacts !== void 0 ? [metric("Contactos", number(data.contacts))] : [],
        ...data.opportunities ? [metric("Oportunidades abiertas", number(data.opportunities.count), `${money2(data.opportunities.value)} en juego`)] : []
      ];
      const stages = data.opportunities ? asList(data.opportunities.stages) : [];
      let funnel;
      if (!stages.length) funnel = empty("Todavía no hay oportunidades abiertas en esta cuenta.");
      else {
        const byValue = stages.some((s) => Number(s.value) > 0);
        funnel = bars(stages.map((stage) => ({
          l: stage.name,
          v: byValue ? Number(stage.value) || 0 : Number(stage.count) || 0,
          label: byValue ? money2(stage.value) ?? "—" : `${number(stage.count)}`,
          small: `${number(stage.count)} oportunidad${stage.count === 1 ? "" : "es"}`,
          tip: `<b>${esc3(stage.name)}</b><br>${number(stage.count)} oportunidad${stage.count === 1 ? "" : "es"}<br>${esc3(money2(stage.value) ?? "—")}`
        })), {});
      }
      const heading = sel.kind === "today" ? "Tu día" : cap(nowLabel());
      $("#dashboard-body").innerHTML = `${sec2(esc3(heading), "Conversaciones, leads y oportunidades abiertas de la cuenta.")}
      <div class="grid g-hero">
        <section class="hero"><div><p class="t-label">${esc3(heroLabel)}</p><p class="t-hero" style="margin-top:10px">${heroValue === null ? "—" : esc3(heroValue)}</p><p class="f">${esc3(heroNote)}</p></div><div class="goal">${heroPair}</div></section>
        ${metricGrid(tiles2)}
      </div>
      <section id="won-team" hidden></section>
      ${slotsDia()}
      ${sec2("Embudo por etapa", "Cuánto hay en juego en cada etapa de tus embudos.")}
      ${panel("Oportunidades abiertas", "Ordenadas por monto, de la etapa con más dinero a la que menos.", funnel + (data.opportunities?.sampled ? '<p class="src">Se resumen las oportunidades abiertas más recientes.</p>' : ""))}
      <div id="board-month">${monthBlock(month2)}</div>
      ${slotEficacia()}`;
      paintWonTeam();
      bindMonth();
      container.querySelectorAll("[data-self]").forEach((b) => b.onclick = selfDialog);
      container.querySelectorAll("[data-go-waiting]").forEach((b) => b.onclick = () => window.dispatchEvent(new CustomEvent("agora:goto", { detail: { view: "inbox", filter: "waiting" } })));
    }
    function monthBlock(month2) {
      if (!month2) return sec2("Rentabilidad del mes") + skeleton("lines", 3);
      if (month2.error) return sec2("Rentabilidad del mes") + panel("No se pudo leer el mes", "", empty(month2.error));
      if (!month2.profitability || !month2.settings || !month2.goal) return sec2("Rentabilidad del mes") + panel("El resumen del mes no llegó completo", "", empty("Vuelve a entrar en un momento."));
      const p = month2.profitability, word = month2.settings?.closingWord || "cierres", one = word.replace(/s$/, "");
      const missing = asList(month2.missing);
      const NAMES = { ads: "lo invertido en publicidad", fixedCosts: "los costos fijos", teamGoal: "la meta del equipo" };
      const WHY = {
        permission: "La clave de la cuenta no tiene permiso para leer los anuncios. Pídele a quien administra Ágora que lo añada.",
        not_connected: "La cuenta no tiene una cuenta publicitaria de Meta conectada.",
        no_spend: "La cuenta publicitaria no informa gasto para este mes.",
        unreadable: "La cuenta publicitaria respondió con un formato que no se pudo leer."
      };
      const adsLine = p.adsFromAccount !== null && p.adsFromAccount !== void 0 ? `<p class="src">Publicidad: ${esc3(money2(p.adsFromAccount))} según la cuenta publicitaria de Meta conectada${p.adsOther ? ` + ${esc3(money2(p.adsOther))} de otros gastos indicados aparte` : ""}.</p>` : p.adsReason && p.adsReason !== "restricted" ? `<div class="note-gold warn">${esc3(WHY[p.adsReason] || "La cuenta no ha informado lo invertido en publicidad.")}${p.adsOther ? ` Se usan ${esc3(money2(p.adsOther))} indicados aparte.` : ""}</div>` : "";
      const warn = missing.length ? `<div class="note-gold">Faltan por indicar ${esc3(missing.map((k) => NAMES[k] || k).join(", "))}. ${month2.canEdit ? "Pulsa «Cifras del mes» para completarlos." : "Pídeselo a quien administra Ágora."}</div>` : "";
      const actions = `<div class="acts"><label class="fld">Mes<select id="board-period">${monthOptions(month2.period)}</select></label>${month2.canEdit ? '<button type="button" data-board-settings class="btn btn-cta">Cifras del mes</button>' : ""}</div>`;
      const sales = month2.sales || {};
      const tiles2 = [
        ...!restricted ? [metric("Ventas ganadas del mes", number(sales.closings ?? 0), `${money2(sales.total ?? 0)} en total`)] : [],
        metric("Mis ventas del mes", month2.mine ? number(month2.mine.closings) : null, month2.mine ? `${money2(month2.mine.sold)} ganados` : unlinkedNote()),
        metric("Ganancia real del mes", money2(p.net), p.net === null ? "Falta indicar los costos fijos" : `Después de ${money2(p.fixedCosts)} en costos fijos`),
        metric(`Costo por ${one}`, money2(p.costPerClosing), p.costPerClosing === null ? "Falta lo invertido en publicidad" : `${money2(p.ads)} entre ${number(sales.closings)} ${word}`),
        metric("Ventas por cada 100 invertido", money2(p.returnPerHundred)),
        metric("Costos de mercadeo y ventas", money2(p.marketingCost), p.marketingCost === null ? "Faltan datos" : `Fijos ${money2(p.fixedCosts ?? 0)} · variables ${money2(p.ads ?? 0)}`),
        metric(`Comisiones del equipo (${commissionText(month2.commission, word)})`, money2(p.commissions), month2.commission?.kind === "per_closing" ? `${number(sales.closings)} ${word}` : `Sobre ${money2(p.sales)} vendidos`)
      ];
      const goal = month2.goal;
      const progress = Math.max(0, Math.min(100, Number(goal.progress) || 0));
      const counts = asList(month2.team).reduce((acc, row) => {
        acc[row.state] = (acc[row.state] || 0) + 1;
        return acc;
      }, {});
      const goalKind = goal.progress === null || goal.progress === void 0 ? "" : goal.progress >= month2.settings.green ? "ok" : goal.progress >= month2.settings.amber ? "warn" : "bad";
      const goalText = goal.target === null ? "Indica la meta del mes para ver el avance." : goal.missing > 0 ? `Faltan ${money2(goal.missing)} para la meta de ${money2(goal.target)}.` : `Meta de ${money2(goal.target)} superada.`;
      const lead = p.profit === null ? "Indica lo invertido para ver la ganancia." : p.marginPerUnit !== null ? `De cada 1,00 vendido quedan ${money2(p.marginPerUnit)}.` : "";
      return `${sec2(`Rentabilidad de ${esc3(monthName(month2.period))}`, "Lo vendido, lo que se va en publicidad y comisiones, y lo que queda.", actions)}
      ${adsLine}${warn}
      <div class="grid g-hero mt">
        <section class="hero"><div><p class="t-label">Ganancia después de publicidad y comisiones</p><p class="t-hero" style="margin-top:10px">${p.profit === null ? "—" : esc3(money2(p.profit))}</p>${lead ? `<p class="f">${esc3(lead)}</p>` : ""}</div><div class="goal">${waterfall(p)}</div></section>
        ${metricGrid(tiles2)}
      </div>
      ${origin(month2, word)}
      ${sec2("Desempeño del equipo de ventas", `Verde desde ${number(month2.settings.green)} %, ámbar desde ${number(month2.settings.amber)} %, rojo por debajo.`)}
      ${panel(
        "Avance frente a la meta",
        "",
        `<div class="goal flat"><div class="pair"><span>Avance del equipo frente a su meta</span><b>${goal.progress === null ? "—" : number(Math.round(goal.progress)) + " %"}</b></div>
          <div class="track" role="img" aria-label="Avance del equipo: ${goal.progress === null ? "sin dato" : number(Math.round(goal.progress)) + " %"}"><span class="fill ${goalKind}" style="width:${progress}%"></span></div>
          <p class="f">${esc3(goalText)}</p>
          <div class="lts"><span class="lt lt-ok"><i aria-hidden="true"></i>${number(counts.lograda || 0)} con la meta lograda</span><span class="lt lt-warn"><i aria-hidden="true"></i>${number(counts.cerca || 0)} cerca de la meta</span><span class="lt lt-bad"><i aria-hidden="true"></i>${number(counts.apoyo || 0)} necesitan apoyo</span></div></div>
         ${teamTable(month2)}
         <p class="src">${month2.sales.partial ? "Se resumen los primeros cierres del mes. " : ""}${month2.sales.counted !== null && month2.sales.counted !== void 0 ? `${number(month2.sales.counted)} ${esc3(word)} en el mes.` : ""}</p>`
      )}`;
    }
    function paintWonTeam() {
      const slot = container.querySelector("#won-team");
      if (!slot) return;
      const team2 = wonAlertState()?.team;
      if (!team2 || !team2.missing) {
        slot.hidden = true;
        slot.innerHTML = "";
        return;
      }
      slot.hidden = false;
      const sellers = asList(team2.bySeller);
      slot.innerHTML = `${sec2("Ventas ganadas sin monto", "Completa el monto para que cuenten en la rentabilidad del mes.")}
      ${panel(
        `${number(team2.missing)} ${team2.missing === 1 ? "venta sin monto este mes" : "ventas sin monto este mes"}`,
        "Por quién las cerró.",
        bars(sellers.map((b) => ({ l: b.name, v: Number(b.missing) || 0, label: number(b.missing), kind: "warn", tip: `<b>${esc3(b.name)}</b><br>${number(b.missing)} sin monto` }))),
        '<button type="button" class="btn btn-sm btn-tint" data-go-won>Ver y completar en Ganadas</button>'
      )}`;
      slot.querySelector("[data-go-won]").onclick = () => {
        workspace.pendingOppTab = "won";
        window.dispatchEvent(new CustomEvent("agora:goto", { detail: { view: "board" } }));
      };
    }
    function onWon() {
      if (!container.isConnected || container.dataset.view !== "dashboard") {
        window.removeEventListener("agora:won-alert", onWon);
        return;
      }
      paintWonTeam();
    }
    async function selfDialog() {
      const dialog2 = document.querySelector("#action-dialog"), content = document.querySelector("#dialog-content");
      const wrap = (inner) => `<div class="tv3 tv3-dialog"><h2>¿Quién eres en esta subcuenta?</h2>${inner}</div>`;
      content.innerHTML = wrap('<p class="lede">Leyendo los usuarios…</p>');
      if (!dialog2.open) dialog2.showModal();
      try {
        const data = await request(`workspace/self?connectionId=${encodeURIComponent(connection)}`);
        const rows2 = Array.isArray(data.options) ? data.options : [];
        content.innerHTML = wrap(`<p class="lede">Con esto, «Leads asignados a mí» y «Mis ventas del mes» cuentan lo que tiene asignado ese usuario. Se guarda sólo para ti en esta subcuenta.</p>
       <form id="self-form" class="form"><label class="fld">Soy<select name="who"><option value="">Ninguno (no mostrar mis cifras)</option>${rows2.map((r) => `<option value="${esc3(r.id)}"${r.id === (data.linked || data.detected) ? " selected" : ""}>${esc3(r.name)}${r.email ? ` · ${esc3(r.email)}` : ""}</option>`).join("")}</select></label>
       <div class="form-ft"><button class="btn btn-cta">Guardar</button><button type="button" class="btn" data-close-dialog>Cerrar</button><p id="self-result" role="status" class="form-msg"></p></div></form>`);
        content.querySelector("[data-close-dialog]").onclick = () => dialog2.close();
        content.querySelector("#self-form").onsubmit = async (e) => {
          e.preventDefault();
          const result = content.querySelector("#self-result");
          result.textContent = "Guardando…";
          try {
            await request(`workspace/self?connectionId=${encodeURIComponent(connection)}`, "PUT", csrf2, { externalUserId: e.target.elements.who.value || null });
            dialog2.close();
            $("#dashboard-body").innerHTML = skeleton("tiles", 4);
            load();
          } catch (error) {
            result.textContent = friendly(error);
          }
        };
      } catch (error) {
        content.innerHTML = wrap(`<p class="lede">${esc3(friendly(error))}</p>`);
      }
    }
    function bindMonth() {
      const pick = $("#board-period");
      if (pick) pick.onchange = () => {
        if (/^\d{4}-\d{2}$/.test(pick.value)) choose({ kind: "monthpick", month: pick.value });
      };
      const settings = $("[data-board-settings]");
      if (settings) settings.onclick = () => openSettings();
    }
    function openSettings() {
      const dialog2 = document.querySelector("#action-dialog");
      const s = month.settings || {};
      const rows2 = asList(month.team).filter((row) => row.id).map((row) => `<label class="fld">${esc3(row.name)}<input type="number" min="0" step="0.01" data-goal="${esc3(row.id)}" value="${row.goal ?? ""}" placeholder="Meta del mes"></label>`).join("");
      document.querySelector("#dialog-content").innerHTML = `<div class="tv3 tv3-dialog"><p class="t-label">${esc3(monthName(month.period))}</p>
      <h2>Cifras del mes</h2>
      <p class="lede">Lo que la cuenta no sabe: lo que invertiste, lo que cuesta mantener la operación y las metas. Se guarda sólo para este mes y esta subcuenta.</p>
      <form id="board-form" class="form">
        <p class="form-msg">Lo invertido en Meta lo informa la cuenta publicitaria conectada. Aquí sólo lo que no pasa por ella.</p>
        <label class="fld">Otros gastos de publicidad (fuera de Meta)<input type="number" min="0" step="0.01" name="otherAds" value="${s.otherAds ?? ""}"></label>
        <label class="fld">Costos fijos del mes<input type="number" min="0" step="0.01" name="fixedCosts" value="${s.fixedCosts ?? ""}"></label>
        <label class="fld">Meta de ventas del equipo<input type="number" min="0" step="0.01" name="teamGoal" value="${s.teamGoal ?? ""}"></label>
        <label class="fld">¿Cómo llamas a cada cierre?<input name="closingWord" maxlength="24" value="${esc3(s.closingWord || "cierres")}" placeholder="estudiantes, clientes, pólizas…"></label>
        <p class="form-sec">Umbrales del semáforo</p>
        <div class="grid g2 g-keep"><label class="fld">Verde desde (%)<input type="number" min="0" max="100" step="1" name="green" value="${s.green ?? 100}"></label>
        <label class="fld">Ámbar desde (%)<input type="number" min="0" max="100" step="1" name="amber" value="${s.amber ?? 70}"></label></div>
        ${rows2 ? `<p class="form-sec">Metas por asesora</p><div class="grid g2 g-keep">${rows2}</div>` : ""}
        <div class="form-ft"><button class="btn btn-cta">Guardar cifras</button><p id="board-form-status" class="form-msg" role="status"></p></div>
      </form></div>`;
      dialog2.showModal();
      const form = document.querySelector("#board-form");
      form.onsubmit = async (event) => {
        event.preventDefault();
        const status = form.querySelector("#board-form-status"), button = form.querySelector("button.btn-cta");
        button.disabled = true;
        status.textContent = "Guardando…";
        const payload = Object.fromEntries(["otherAds", "fixedCosts", "teamGoal", "closingWord", "green", "amber"].map((k) => [k, form.elements[k].value]));
        payload.goals = Object.fromEntries([...form.querySelectorAll("[data-goal]")].map((input) => [input.dataset.goal, input.value]));
        try {
          await request(`workspace/board-month?connectionId=${encodeURIComponent(connection)}&period=${period}`, "POST", csrf2, payload);
          month = await request(`workspace/board-month?connectionId=${encodeURIComponent(connection)}&period=${period}`);
          if (!active()) return;
          dialog2.close();
          $("#board-month").innerHTML = monthBlock(month);
          bindMonth();
        } catch (error) {
          status.textContent = friendly(error);
          button.disabled = false;
        }
      };
    }
    await load();
  }

  // agora/contact-editor.js
  var esc4 = (s) => String(s ?? "").replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" })[c]);
  var asList2 = (value) => Array.isArray(value) ? value : [];
  var EDITABLE = [["name", "Nombre completo", "text"], ["firstName", "Nombre", "text"], ["lastName", "Apellido", "text"], ["email", "Correo", "email"], ["phone", "Teléfono", "tel"], ["companyName", "Empresa", "text"], ["website", "Sitio web", "url"], ["address1", "Dirección", "text"], ["city", "Ciudad", "text"], ["state", "Provincia o estado", "text"], ["postalCode", "Código postal", "text"], ["country", "País", "text"], ["source", "Fuente", "text"], ["timezone", "Zona horaria", "text"], ["dateOfBirth", "Fecha de nacimiento", "date"]];
  var READ_ONLY_TYPES = ["FILE_UPLOAD", "SIGNATURE"];
  function customControl(field, current2) {
    const id = esc4(field.id), name2 = esc4(field.name || "Campo personalizado"), hint = field.placeholder ? ` placeholder="${esc4(field.placeholder)}"` : "";
    const value = current2?.value ?? "";
    const chosen = asList2(current2?.values).length ? asList2(current2.values) : value ? String(value).split(", ").filter(Boolean) : [];
    if (READ_ONLY_TYPES.includes(field.dataType)) return `<label>${name2}<input value="${esc4(value)}" disabled></label>`;
    if (field.dataType === "CHECKBOX") return `<label class="live-check"><input type="checkbox" data-field="${id}" data-type="CHECKBOX"${current2?.checked || value === "Sí" ? " checked" : ""}>${name2}</label>`;
    if (field.dataType === "MULTIPLE_OPTIONS" && field.options?.length)
      return `<div class="live-field-group" data-field="${id}" data-type="MULTIPLE_OPTIONS"><span class="live-field-name">${name2}</span>${field.options.map((option) => `<label class="live-check"><input type="checkbox" data-option="${esc4(option)}"${chosen.includes(option) ? " checked" : ""}>${esc4(option)}</label>`).join("")}</div>`;
    if ((field.dataType === "SINGLE_OPTIONS" || field.dataType === "RADIO") && field.options?.length)
      return `<label>${name2}<select data-field="${id}" data-type="SINGLE_OPTIONS"><option value="">Sin elegir</option>${field.options.map((option) => `<option${option === value ? " selected" : ""}>${esc4(option)}</option>`).join("")}</select></label>`;
    if (field.dataType === "LARGE_TEXT" || field.dataType === "TEXTBOX_LIST")
      return `<label>${name2}<textarea data-field="${id}" data-type="${esc4(field.dataType)}" rows="3" maxlength="5000"${hint}>${esc4(value)}</textarea></label>`;
    const type = field.dataType === "NUMERICAL" || field.dataType === "MONETORY" ? "number" : field.dataType === "DATE" ? "date" : field.dataType === "EMAIL" ? "email" : field.dataType === "PHONE" ? "tel" : "text";
    return `<label>${name2}<input type="${type}" data-field="${id}" data-type="${esc4(field.dataType)}" value="${esc4(value)}" maxlength="1000"${hint}></label>`;
  }
  function groupByFolder(items, directory = []) {
    const dir = asList2(directory), folderOf = new Map(dir.map((f) => [f.id, f.folder || null]));
    const order = [];
    for (const f of dir) {
      const k = f.folder || null;
      if (!order.includes(k)) order.push(k);
    }
    const groups = /* @__PURE__ */ new Map();
    for (const item of asList2(items)) {
      const k = folderOf.get(item.id) ?? null;
      if (!groups.has(k)) groups.set(k, []);
      groups.get(k).push(item);
    }
    const keys = [...groups.keys()].sort((a, b) => (a === null) - (b === null) || (order.indexOf(a) < 0 ? 999 : order.indexOf(a)) - (order.indexOf(b) < 0 ? 999 : order.indexOf(b)));
    const named = keys.some((k) => k !== null);
    return keys.map((k) => ({ title: k ?? (named ? "Otros campos" : "Campos personalizados"), items: groups.get(k) }));
  }
  var GROUPS = [
    ["Contacto", ["name", "firstName", "lastName", "email", "phone"]],
    ["Empresa", ["companyName", "website"]],
    ["Dirección", ["address1", "city", "state", "postalCode", "country"]],
    ["Seguimiento", ["source", "timezone", "dateOfBirth"]]
  ];
  var WIDE = ["name", "email", "address1", "website"];
  function contactForm(profile2, directory = [], assignees = []) {
    const byKey = Object.fromEntries(EDITABLE.map((row) => [row[0], row]));
    const control = (key) => {
      const [k, label, type] = byKey[key];
      return `<label class="${WIDE.includes(k) ? "wide" : ""}">${esc4(label)}<input type="${type}" name="${k}" value="${esc4(profile2[k] ?? "")}" maxlength="250"></label>`;
    };
    const used = new Set(GROUPS.flatMap(([, keys]) => keys));
    const rest = EDITABLE.map((row) => row[0]).filter((key) => !used.has(key));
    const groups = GROUPS.map(([title, keys], i) => {
      const inner = keys.filter((key) => byKey[key]).map(control).join("") + (i === GROUPS.length - 1 ? rest.map(control).join("") + (asList2(assignees).length ? `<label class="wide">Responsable<select name="assignedTo"><option value="">Sin asignar</option>${assignees.map((user) => `<option value="${esc4(user.id)}"${user.id === profile2.assignedTo ? " selected" : ""}>${esc4(user.name)}</option>`).join("")}</select></label>` : "") + `<label class="contact-switch wide"><input type="checkbox" name="dnd"${profile2.dnd ? " checked" : ""}><span class="switch-track" aria-hidden="true"></span><span>No contactar<small>No se le enviarán mensajes desde ningún canal.</small></span></label>` : "");
      return `<fieldset class="contact-group"><legend>${esc4(title)}</legend><div class="contact-grid">${inner}</div></fieldset>`;
    }).join("");
    const values = new Map(asList2(profile2.customFields).map((field) => [field.id, field]));
    const defined = asList2(directory);
    const extra = asList2(profile2.customFields).filter((field) => !defined.some((d) => d.id === field.id)).map((field) => ({ id: field.id, name: "Campo personalizado", dataType: "TEXT", options: [] }));
    const customGroups = groupByFolder([...defined, ...extra], defined);
    const filledId = new Set(asList2(profile2.customFields).filter((f) => f.value !== "" && f.value != null || asList2(f.values).length || f.checked).map((f) => f.id));
    const customHtml = customGroups.length ? customGroups.map((g, i) => {
      const done = g.items.filter((f) => filledId.has(f.id)).length;
      return `<details class="contact-group fold" data-fold${i === 0 ? " open" : ""}><summary><span>${esc4(g.title)}</span><small>${done} de ${g.items.length}</small></summary><div class="contact-grid">${g.items.map((field) => customControl(field, values.get(field.id))).join("")}</div></details>`;
    }).join("") : '<fieldset class="contact-group"><legend>Campos personalizados</legend><span class="muted">Esta cuenta no define campos personalizados.</span></fieldset>';
    const finder = defined.length > 8 ? '<label class="field-finder"><span class="sr-only">Buscar un campo</span><input type="search" id="live-field-find" placeholder="Buscar un campo…" autocomplete="off"></label>' : "";
    const tags = asList2(profile2.tags).map((tag) => `<span class="tag">${esc4(tag)}<button type="button" data-drop-tag="${esc4(tag)}" aria-label="Quitar etiqueta ${esc4(tag)}">×</button></span>`).join("") || '<span class="muted">Sin etiquetas</span>';
    return `<form id="live-contact-form" class="live-contact-form contact-editor"><header class="live-profile-header"><div><span class="section-label">Editar ficha</span><h2>${esc4(profile2.name)}</h2></div><button type="button" id="live-edit-cancel" aria-label="Cancelar edición">×</button></header>
  ${groups}
  ${finder}${customHtml}
  <fieldset class="contact-group"><legend>Etiquetas</legend><div class="live-profile-tags" id="live-tag-list">${tags}</div>
   <div class="tag-add"><label class="sr-only" for="live-new-tag">Añadir etiqueta</label><input id="live-new-tag" maxlength="100" placeholder="Nueva etiqueta"><button type="button" id="live-add-tag">Añadir</button></div></fieldset>
  <div class="contact-save"><p id="live-contact-status" role="status"></p><button type="button" class="link-button" data-cancel-edit>Cancelar</button><button class="primary" id="live-save-contact">Guardar ficha</button></div></form>`;
  }
  function readContactForm(form, contact) {
    const fields = {};
    for (const [key] of EDITABLE) {
      const input = form.querySelector(`[name="${key}"]`);
      if (input && input.value.trim() !== String(contact[key] ?? "")) fields[key] = input.value.trim();
    }
    const dnd = form.querySelector('[name="dnd"]');
    if (dnd && dnd.checked !== (contact.dnd === true)) fields.dnd = dnd.checked;
    const assigned = form.querySelector('[name="assignedTo"]');
    if (assigned && assigned.value !== String(contact.assignedTo ?? "")) fields.assignedTo = assigned.value;
    const previous = new Map(asList2(contact.customFields).map((field) => [field.id, field]));
    const customFields = [];
    for (const node of form.querySelectorAll("[data-field]")) {
      if (node.disabled) continue;
      const id = node.dataset.field, was = previous.get(id);
      let value;
      if (node.dataset.type === "MULTIPLE_OPTIONS") value = [...node.querySelectorAll("[data-option]")].filter((box) => box.checked).map((box) => box.dataset.option);
      else if (node.type === "checkbox") value = node.checked;
      else value = node.value;
      const before = Array.isArray(value) ? asList2(was?.values).join("\0") : typeof value === "boolean" ? was?.checked === true : String(was?.value ?? "");
      const now = Array.isArray(value) ? value.join("\0") : value;
      if (now !== before) customFields.push({ id, value });
    }
    if (customFields.length) fields.customFields = customFields;
    return fields;
  }
  function bindTagEditor(root, contact, loadTags = null) {
    const state2 = { added: [], dropped: [] };
    const list = root.querySelector("#live-tag-list");
    const input = root.querySelector("#live-new-tag");
    const box = root.querySelector(".tag-add");
    let known = null, failed = false;
    const suggest = document.createElement("div");
    suggest.className = "tag-suggest";
    suggest.hidden = true;
    box.after(suggest);
    const current2 = () => [...list.querySelectorAll("[data-drop-tag]")].map((b) => b.dataset.dropTag);
    const paintSuggest = () => {
      if (known === null && !failed) {
        suggest.hidden = false;
        suggest.innerHTML = '<span class="muted">Buscando etiquetas…</span>';
        return;
      }
      if (failed) {
        suggest.hidden = false;
        suggest.innerHTML = '<span class="muted">No se pudieron cargar las etiquetas existentes; puedes escribir una.</span>';
        return;
      }
      const typed = input.value.trim().toLowerCase(), have = current2().map((t) => t.toLowerCase());
      const options = known.filter((t) => !have.includes(t.toLowerCase()) && (!typed || t.toLowerCase().includes(typed))).slice(0, 40);
      suggest.hidden = false;
      suggest.innerHTML = options.length ? options.map((t) => `<button type="button" class="tag tag-option" data-pick-tag="${esc4(t)}">${esc4(t)}</button>`).join("") : `<span class="muted">${known.length ? "Ninguna etiqueta coincide. «Añadir» crea una nueva." : "Esta cuenta todavía no tiene etiquetas."}</span>`;
      suggest.querySelectorAll("[data-pick-tag]").forEach((b) => b.onclick = () => {
        addValue(b.dataset.pickTag);
        input.focus();
      });
    };
    const ensure = () => {
      if (known !== null || !loadTags) {
        if (!loadTags) {
          failed = true;
        }
        return Promise.resolve();
      }
      return loadTags().then((items) => {
        known = items;
      }).catch(() => {
        failed = true;
      });
    };
    const open = () => {
      paintSuggest();
      ensure().then(paintSuggest);
    };
    const rebind = () => root.querySelectorAll("[data-drop-tag]").forEach((button) => button.onclick = () => {
      const tag = button.dataset.dropTag;
      state2.added = state2.added.filter((t) => t !== tag);
      if (asList2(contact.tags).includes(tag) && !state2.dropped.includes(tag)) state2.dropped.push(tag);
      button.parentElement.remove();
      if (!suggest.hidden) paintSuggest();
    });
    const addValue = (value) => {
      value = value.trim();
      if (!value) return;
      if (current2().some((t) => t.toLowerCase() === value.toLowerCase())) {
        input.value = "";
        return;
      }
      if (!state2.added.includes(value)) state2.added.push(value);
      state2.dropped = state2.dropped.filter((t) => t !== value);
      input.value = "";
      list.querySelector(".muted")?.remove();
      list.insertAdjacentHTML("beforeend", `<span class="tag">${esc4(value)}<button type="button" data-drop-tag="${esc4(value)}" aria-label="Quitar etiqueta ${esc4(value)}">×</button></span>`);
      rebind();
      paintSuggest();
    };
    root.querySelector("#live-add-tag").onclick = () => {
      if (input.value.trim()) addValue(input.value);
      else {
        open();
        input.focus();
      }
    };
    input.onfocus = open;
    input.oninput = () => {
      if (known !== null || failed) paintSuggest();
      else open();
    };
    input.onkeydown = (event) => {
      if (event.key === "Enter") {
        event.preventDefault();
        addValue(input.value);
      }
    };
    rebind();
    return state2;
  }
  var FIELD_BOX = "label,.live-field-group";
  function bindEditorExtras(root, contact, tagState, onCancel) {
    const form = root.querySelector("#live-contact-form");
    if (!form) return { dirty: () => false };
    const save2 = form.querySelector("#live-save-contact");
    const changedCount = () => {
      const f = readContactForm(form, contact);
      return Object.keys(f).filter((k) => k !== "customFields").length + (f.customFields ? f.customFields.length : 0) + (tagState ? tagState.added.length + tagState.dropped.length : 0);
    };
    const refresh = () => {
      form.querySelectorAll(".changed").forEach((n2) => n2.classList.remove("changed"));
      const f = readContactForm(form, contact);
      for (const key of Object.keys(f)) {
        if (key === "customFields") continue;
        form.querySelector(`[name="${key}"]`)?.closest("label")?.classList.add("changed");
      }
      for (const item of asList2(f.customFields)) form.querySelector(`[data-field="${CSS.escape(item.id)}"]`)?.closest(FIELD_BOX)?.classList.add("changed");
      const n = changedCount();
      if (save2) save2.textContent = n ? `Guardar ficha (${n})` : "Guardar ficha";
    };
    form.addEventListener("input", refresh);
    form.addEventListener("change", refresh);
    const tagList = form.querySelector("#live-tag-list");
    if (tagList) new MutationObserver(refresh).observe(tagList, { childList: true });
    const find = form.querySelector("#live-field-find");
    if (find) find.oninput = () => {
      const q = find.value.trim().toLowerCase();
      form.querySelectorAll("details[data-fold]").forEach((group) => {
        let any = false;
        group.querySelectorAll(".contact-grid > *").forEach((node) => {
          const hit = !q || node.textContent.toLowerCase().includes(q);
          node.hidden = !hit;
          if (hit) any = true;
        });
        group.hidden = !any;
        if (q && any) group.open = true;
      });
    };
    const status = form.querySelector("#live-contact-status");
    let armed = null;
    const guard = (button) => {
      if (!button) return;
      const original = button.textContent;
      button.onclick = () => {
        if (changedCount() === 0 || armed === button) {
          onCancel();
          return;
        }
        armed = button;
        button.textContent = "Descartar los cambios";
        if (status) status.textContent = `Tienes ${changedCount()} cambio${changedCount() === 1 ? "" : "s"} sin guardar. Pulsa otra vez para descartarlos.`;
        setTimeout(() => {
          if (armed === button) {
            armed = null;
            button.textContent = original;
            if (status && status.textContent.startsWith("Tienes")) status.textContent = "";
          }
        }, 4e3);
      };
    };
    guard(form.querySelector("[data-cancel-edit]"));
    guard(form.querySelector("#live-edit-cancel"));
    refresh();
    return { dirty: () => changedCount() > 0 };
  }

  // agora/opportunity-editor.js
  var esc5 = (s) => String(s ?? "").replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" })[c]);
  var asList3 = (value) => Array.isArray(value) ? value : [];
  var STATUSES = [["open", "Abierta"], ["won", "Ganada"], ["lost", "Perdida"], ["abandoned", "Abandonada"]];
  function opportunityForm({ pipelines = [], assignees = [], opportunity = null, title = "" } = {}) {
    const list = asList3(pipelines);
    const current2 = opportunity || {};
    const chosen = list.find((p) => p.id === current2.pipelineId) || list[0] || { stages: [] };
    const stages = asList3(chosen.stages);
    return `<form class="opportunity-form" data-opportunity="${esc5(current2.id ?? "")}">
   <div class="section-label">${esc5(title || (current2.id ? "Editar oportunidad" : "Nueva oportunidad"))}</div>
   <label>Nombre<input name="name" maxlength="190" value="${esc5(current2.name ?? "")}" required></label>
   <label>Embudo<select name="pipelineId">${list.map((p) => `<option value="${esc5(p.id)}"${p.id === chosen.id ? " selected" : ""}>${esc5(p.name)}</option>`).join("")}</select></label>
   <label>Etapa<select name="pipelineStageId">${stages.map((stage) => `<option value="${esc5(stage.id)}"${stage.id === current2.pipelineStageId ? " selected" : ""}>${esc5(stage.name)}</option>`).join("")}</select></label>
   <label>Valor<input name="monetaryValue" type="number" min="0" max="999999999" step="0.01" value="${esc5(typeof current2.monetaryValue === "number" ? current2.monetaryValue : "")}"></label>
   <label>Responsable<select name="assignedTo"><option value="">Sin asignar</option>${asList3(assignees).map((user) => `<option value="${esc5(user.id)}"${user.id === current2.assignedTo ? " selected" : ""}>${esc5(user.name)}</option>`).join("")}</select></label>
   <label>Estado<select name="status">${STATUSES.map(([value, label]) => `<option value="${value}"${value === (current2.status ?? "open") ? " selected" : ""}>${label}</option>`).join("")}</select></label>
   <div class="opportunity-form-actions"><button class="primary" data-save>${current2.id ? "Guardar cambios" : "Crear oportunidad"}</button><button type="button" data-cancel>Cancelar</button></div>
   <p data-status role="status"></p>
  </form>`;
  }
  function bindPipelineChoice(form, pipelines) {
    const list = asList3(pipelines);
    const select = form.querySelector('[name="pipelineId"]'), stages = form.querySelector('[name="pipelineStageId"]');
    if (!select || !stages) return;
    select.onchange = () => {
      const chosen = list.find((p) => p.id === select.value);
      stages.innerHTML = asList3(chosen?.stages).map((stage) => `<option value="${esc5(stage.id)}">${esc5(stage.name)}</option>`).join("");
    };
  }
  function readOpportunityForm(form, opportunity = null) {
    const read = (name2) => form.querySelector(`[name="${name2}"]`)?.value ?? "";
    const value = read("monetaryValue");
    const all = {
      name: read("name").trim(),
      pipelineId: read("pipelineId"),
      pipelineStageId: read("pipelineStageId"),
      monetaryValue: value === "" ? 0 : Number(value),
      assignedTo: read("assignedTo"),
      status: read("status")
    };
    if (!opportunity) return all;
    const was = {
      name: opportunity.name ?? "",
      pipelineId: opportunity.pipelineId ?? "",
      pipelineStageId: opportunity.pipelineStageId ?? "",
      monetaryValue: typeof opportunity.monetaryValue === "number" ? opportunity.monetaryValue : 0,
      assignedTo: opportunity.assignedTo ?? "",
      status: opportunity.status ?? "open"
    };
    const changed = {};
    for (const [key, now] of Object.entries(all)) if (now !== was[key]) changed[key] = now;
    if (changed.pipelineId !== void 0 && changed.pipelineStageId === void 0) changed.pipelineStageId = all.pipelineStageId;
    return changed;
  }

  // agora/message-view.js
  var esc6 = (s) => String(s ?? "").replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" })[c]);
  var KINDS2 = [
    ["image", /\.(png|jpe?g|gif|webp|avif|bmp|svg|heic)(\?|$)/i],
    ["audio", /\.(mp3|m4a|aac|ogg|oga|opus|wav|amr)(\?|$)/i],
    ["video", /\.(mp4|mov|webm|3gp|m4v)(\?|$)/i],
    ["pdf", /\.pdf(\?|$)/i]
  ];
  function attachmentsOf(message) {
    const raw = Array.isArray(message?.attachments) ? message.attachments : [];
    const files = [];
    for (const item of raw.slice(0, 10)) {
      const url = typeof item === "string" ? item : item && typeof item.url === "string" ? item.url : null;
      if (!url) continue;
      let parsed;
      try {
        parsed = new URL(url);
      } catch {
        continue;
      }
      if (parsed.protocol !== "https:") continue;
      let name2;
      try {
        name2 = decodeURIComponent(parsed.pathname.split("/").filter(Boolean).pop() || "archivo");
      } catch {
        name2 = "archivo";
      }
      const kind = (KINDS2.find(([, test]) => test.test(parsed.pathname)) || ["file"])[0];
      files.push({ url: parsed.href, name: name2.slice(0, 80), kind });
    }
    return files;
  }
  var ICONS = {
    pdf: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 3h8l4 4v14H6z" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="M14 3v4h4M9 13h6M9 16.5h6" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>',
    file: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 12.5 13.6 7a2.6 2.6 0 0 1 3.7 3.7l-7 7a4.2 4.2 0 0 1-6-6l6.6-6.6" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>'
  };
  function attachmentMarkup(file) {
    if (file.kind === "image") return `<button type="button" class="att-image loading" data-view="${esc6(file.url)}" data-name="${esc6(file.name)}" aria-label="Ver la imagen ${esc6(file.name)} a pantalla completa"><img src="${esc6(file.url)}" alt="${esc6(file.name)}" loading="lazy" decoding="async"></button>`;
    if (file.kind === "audio") return `<div class="att-media"><audio controls preload="none" src="${esc6(file.url)}"></audio><a href="${esc6(file.url)}" target="_blank" rel="noopener noreferrer">${esc6(file.name)}</a></div>`;
    if (file.kind === "video") return `<div class="att-media"><video controls preload="metadata" playsinline src="${esc6(file.url)}"></video><a href="${esc6(file.url)}" target="_blank" rel="noopener noreferrer">${esc6(file.name)}</a></div>`;
    return `<a class="att-file" href="${esc6(file.url)}" target="_blank" rel="noopener noreferrer">${ICONS[file.kind] || ICONS.file}<span>${esc6(file.name)}</span><b>Abrir</b></a>`;
  }
  function bindAttachments(root) {
    root.querySelectorAll(".att-image").forEach((button) => {
      const img = button.querySelector("img");
      const done = () => button.classList.remove("loading");
      if (img.complete && img.naturalWidth) done();
      img.addEventListener("load", done, { once: true });
      img.addEventListener("error", () => {
        button.outerHTML = `<a class="att-file broken" href="${esc6(button.dataset.view)}" target="_blank" rel="noopener noreferrer">${ICONS.file}<span>No se pudo cargar la imagen</span><b>Abrir original</b></a>`;
      }, { once: true });
      button.onclick = () => openViewer(button.dataset.view, button.dataset.name);
    });
  }
  function openViewer(url, name2) {
    document.querySelector(".viewer")?.remove();
    const viewer = document.createElement("div");
    viewer.className = "viewer";
    viewer.setAttribute("role", "dialog");
    viewer.setAttribute("aria-modal", "true");
    viewer.setAttribute("aria-label", `Imagen ${name2 || ""}`);
    viewer.innerHTML = `<div class="viewer-bar"><span>${esc6(name2 || "Imagen")}</span><a href="${esc6(url)}" target="_blank" rel="noopener noreferrer">Abrir original</a><button type="button" class="viewer-close" aria-label="Cerrar la imagen">×</button></div><img src="${esc6(url)}" alt="${esc6(name2 || "Imagen")}">`;
    const close = () => {
      viewer.remove();
      document.removeEventListener("keydown", onKey);
      opener?.focus?.();
    };
    const onKey = (event) => {
      if (event.key === "Escape") close();
    };
    const opener = document.activeElement;
    viewer.addEventListener("click", (event) => {
      if (event.target === viewer || event.target.tagName === "IMG") close();
    });
    viewer.querySelector(".viewer-close").onclick = close;
    document.addEventListener("keydown", onKey);
    document.body.appendChild(viewer);
    viewer.querySelector(".viewer-close").focus();
  }
  var STATES2 = {
    pending: ["Enviando…", "wait"],
    queued: ["Enviando…", "wait"],
    scheduled: ["Programado", "wait"],
    sent: ["Enviado", "ok"],
    delivered: ["Entregado", "ok"],
    read: ["Leído", "ok"],
    opened: ["Abierto", "ok"],
    clicked: ["Abierto", "ok"],
    connected: ["Conectada", "ok"],
    received: ["Recibido", "ok"],
    failed: ["No enviado", "bad"],
    undelivered: ["No entregado", "bad"],
    opt_out: ["Se dio de baja", "bad"]
  };
  var CAUSES = {
    TYPE_WHATSAPP: ["Han pasado más de 24 horas desde el último mensaje del contacto: WhatsApp sólo deja escribir con una plantilla aprobada.", "El número no tiene WhatsApp o no está bien escrito.", "El contacto bloqueó el número de la empresa.", "La cuenta de WhatsApp Business tiene un aviso de calidad o de pago pendiente."],
    TYPE_SMS: ["El número no es válido o no admite SMS.", "El contacto respondió STOP y se dio de baja.", "Falta saldo o el registro del número que envía."],
    TYPE_EMAIL: ["La dirección no existe o el correo rebotó.", "El servidor del destinatario lo rechazó."],
    TYPE_INSTAGRAM: ["Han pasado más de 24 horas desde el último mensaje del contacto.", "La cuenta de Instagram se desconectó o el contacto la bloqueó."],
    TYPE_FACEBOOK: ["Han pasado más de 24 horas desde el último mensaje del contacto.", "La página se desconectó o el contacto la bloqueó."]
  };
  var OPT_OUT = "El contacto pidió no recibir mensajes por este canal. No se le puede escribir por aquí hasta que vuelva a escribir él o se reactive.";
  function reasonOf(message) {
    const candidates = [message?.error, message?.errorMessage, message?.meta?.error, message?.meta?.errorMessage, message?.meta?.reason, message?.meta?.error?.message, message?.error?.message];
    for (const value of candidates) if (typeof value === "string" && value.trim()) return value.trim().slice(0, 200);
    return null;
  }
  function statusInfo(message) {
    const [label, tone] = STATES2[message?.status] || ["", ""];
    if (tone !== "bad") return { label, tone, reason: null, causes: [] };
    return { label, tone, reason: reasonOf(message), causes: message.status === "opt_out" ? [OPT_OUT] : CAUSES[message.messageType] || ["Revisa el número o la dirección del contacto y el estado del canal."] };
  }
  function statusMarkup(message, time) {
    const info = statusInfo(message);
    if (message?.direction === "inbound" && info.tone !== "bad") info.label = "";
    const line = `<time>${esc6(time)}${info.label ? ` · <span class="msg-state ${esc6(info.tone)}">${esc6(info.label)}</span>` : ""}</time>`;
    if (info.tone !== "bad") return line;
    const retry = message.direction === "outbound" && typeof message.body === "string" && message.body.trim() && message.status !== "opt_out" ? `<button type="button" class="msg-retry" data-retry="${esc6(message.id)}">Volver a escribirlo</button>` : "";
    return `${line}<details class="msg-why"><summary>Por qué</summary>
    ${info.reason ? `<p><b>Motivo indicado por el canal:</b> ${esc6(info.reason)}</p>` : ""}
    <p>${info.reason ? "Otras causas habituales:" : "La cuenta no indica el motivo. Causas habituales:"}</p>
    <ul>${info.causes.map((c) => `<li>${esc6(c)}</li>`).join("")}</ul>${retry}</details>`;
  }
  var svg = (path) => `<svg viewBox="0 0 24 24" aria-hidden="true" class="ui-icon">${path}</svg>`;
  var UI_ICONS = {
    back: svg('<path d="M15 5l-7 7 7 7" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>'),
    expand: svg('<path d="M4 9V4h5M20 9V4h-5M4 15v5h5M20 15v5h-5" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/>'),
    shrink: svg('<path d="M9 4v5H4M15 4v5h5M9 20v-5H4M15 20v-5h5" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/>'),
    contact: svg('<circle cx="12" cy="8.5" r="3.5" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M5 20c.8-3.6 3.6-5.5 7-5.5s6.2 1.9 7 5.5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>')
  };

  // agora/live-inbox.js
  document.addEventListener("click", (e) => {
    if (e.target.closest?.(".channel-menu")) return;
    document.querySelectorAll(".channel-list:not([hidden])").forEach((m) => {
      m.hidden = true;
      m.parentElement.querySelector(".channel-current")?.setAttribute("aria-expanded", "false");
    });
  });
  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape") document.querySelectorAll(".channel-list:not([hidden])").forEach((m) => {
      m.hidden = true;
      m.parentElement.querySelector(".channel-current")?.setAttribute("aria-expanded", "false");
    });
  });
  var calm = () => {
    try {
      return matchMedia("(prefers-reduced-motion: reduce)").matches;
    } catch {
      return false;
    }
  };
  var scrollEnd = (el, smooth) => el.scrollTo({ top: el.scrollHeight, behavior: smooth && !calm() ? "smooth" : "auto" });
  var drafts = /* @__PURE__ */ new Map();
  ["agora:logout", "agora:account-changing"].forEach(event => window.addEventListener(event, () => {
    drafts.clear();
    for (const key of Object.keys(sessionStorage)) if (key.startsWith("agora-send:")) sessionStorage.removeItem(key);
  }));
  var sendChannels = { TYPE_SMS: "SMS", TYPE_WHATSAPP: "WhatsApp", TYPE_INSTAGRAM: "IG", TYPE_FACEBOOK: "FB", TYPE_EMAIL: "Email", TYPE_INSTAGRAM_COMMENT: "IG", TYPE_FACEBOOK_COMMENT: "FB" };
  var esc7 = (s) => String(s ?? "").replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" })[c]);
  var channel = (value) => ({ TYPE_WHATSAPP: "WhatsApp", TYPE_INSTAGRAM: "Instagram", TYPE_SMS: "SMS", TYPE_EMAIL: "Email", TYPE_FACEBOOK: "Messenger", TYPE_CALL: "Llamada", TYPE_LIVE_CHAT: "Chat web", TYPE_INTERNAL_COMMENTS: "Nota interna", TYPE_INSTAGRAM_COMMENT: "Comentario en Instagram", TYPE_FACEBOOK_COMMENT: "Comentario en Facebook" })[value] || "Conversación";
  var name = (c) => c.contactName || c.fullName || [c.firstName, c.lastName].filter(Boolean).join(" ") || "Contacto sin nombre";
  var initials = (c) => name(c).split(/\s+/).slice(0, 2).map((s) => [...s][0]).join("").toUpperCase();
  var when = (value) => {
    if (!value) return "";
    const d = new Date(typeof value === "string" && /^\d{4}-\d\d-\d\d \d\d:/.test(value) ? value.replace(" ", "T") + "Z" : value);
    if (Number.isNaN(d.getTime())) return "";
    const today = /* @__PURE__ */ new Date();
    today.setHours(0, 0, 0, 0);
    const day = new Date(d);
    day.setHours(0, 0, 0, 0);
    const ago = Math.round((today - day) / 864e5);
    if (ago <= 0) return new Intl.DateTimeFormat("es", { timeStyle: "short" }).format(d);
    if (ago === 1) return "Ayer";
    if (ago < 7) return new Intl.DateTimeFormat("es", { weekday: "short" }).format(d);
    return new Intl.DateTimeFormat("es", { day: "numeric", month: "short" }).format(d);
  };
  var date = (value) => {
    if (!value) return "";
    const d = new Date(typeof value === "string" && /^\d{4}-\d\d-\d\d \d\d:/.test(value) ? value.replace(" ", "T") + "Z" : value);
    return Number.isNaN(d.getTime()) ? "" : new Intl.DateTimeFormat("es", { dateStyle: "short", timeStyle: "short" }).format(d);
  };
  async function request2(path, method = "GET", csrf2 = "", body = null) {
    const r = await fetch((window.AGORA_API || "/api/") + path, { method, cache: "no-store", ...body !== null ? { body: JSON.stringify(body) } : {}, headers: { "Content-Type": "application/json", ...csrf2 ? { "X-CSRF-Token": csrf2 } : {} } });
    let data;
    try {
      data = await r.json();
    } catch {
      const error = Error(`El servidor devolvió una respuesta no válida (HTTP ${r.status}). Reintenta; si persiste, comparte este código.`);
      error.status = r.status;
      throw error;
    }
    if (!r.ok) {
      const error = Error((data.error || "Error de conexión") + (data.retryAfter ? ` Espera ${data.retryAfter} segundos.` : ""));
      error.code = data.code;
      error.status = r.status;
      error.retryAfter = Number(data.retryAfter) || 0;
      throw error;
    }
    return data;
  }
  async function renderLiveInbox(container) {
    container.agoraLiveDispose?.();
    const instance = /* @__PURE__ */ Symbol();
    container.agoraLiveInstance = instance;
    const active = () => container.agoraLiveInstance === instance && container.dataset.view === "inbox";
    container.innerHTML = skeleton("list", 7);
    try {
      let say = function(text = "") {
        autoStatus.textContent = text;
        autoStatus.hidden = text === "";
      }, schedule2 = function(seconds = interval) {
        clearTimeout(timer3);
        if (!canRefresh()) return;
        timer3 = setTimeout(tick, Math.max(1, seconds) * 1e3);
      }, scheduleChat = function(seconds = CHAT_FALLBACK) {
        clearTimeout(pollTimer);
        if (!canRefresh() || !selected || !layout.classList.contains("chat-open")) return;
        pollTimer = setTimeout(pollChat, Math.max(2, seconds) * 1e3 + Math.random()*5000);
      }, schedulePulse = function(seconds = PULSE) {
        clearTimeout(pulseTimer);
        if (!canRefresh()) return;
        pulseTimer = setTimeout(pulseTick, Math.max(2, seconds) * 1e3 + Math.random()*5000);
      }, wake = function() {
        clearTimeout(timer3);
        clearTimeout(chatTimer);
        clearTimeout(pollTimer);
        clearTimeout(pulseTimer);
        if (canRefresh()) {
          say();
          schedule2(Math.max(1, pause - Date.now() / 1e3));
          scheduleChat();
          schedulePulse(1);
        } else say(navigator.onLine ? "En pausa mientras la pantalla está oculta" : "Sin conexión · se reanudará solo");
      }, list = function() {
        const stamp = (c) => {
          const n = Number(c.lastMessageDate);
          return Number.isFinite(n) && n > 0 ? n : Date.parse(c.lastMessageDate) || 0;
        };
        const conRec = (c) => recordatorios.has(c.id) ? 0 : 1;
        const base2 = filter === "waiting" ? conversations.filter((c) => c.lastMessageDirection !== "outbound").sort((a, b) => stamp(a) - stamp(b)) : conversations;
        const visible = [...base2].sort((a, b) => conRec(a) - conRec(b));
        const waited = (c) => {
          const m = Math.max(0, Math.round((Date.now() - stamp(c)) / 6e4));
          return m < 60 ? `${m} min` : m < 1440 ? `${Math.floor(m / 60)} h` : `${Math.floor(m / 1440)} d`;
        };
        $("#live-count").textContent = visible.length;
        const listEl = $("#live-list");
        if (visible.length && !listEl.dataset.painted) setTimeout(() => {
          listEl.dataset.painted = "1";
        }, 1200);
        $("#live-list").innerHTML = visible.map((c) => `<button class="conversation-item live-conversation ${selected?.id === c.id ? "selected" : ""}${recordatorios.has(c.id) ? " con-rec" : ""}" data-live-id="${esc7(c.id)}" aria-label="Abrir conversación de ${esc7(name(c))}" aria-pressed="${selected?.id === c.id}"><span class="avatar">${esc7(initials(c))}</span><span class="conversation-info"><header><strong>${esc7(name(c))}</strong><time title="${esc7(date(c.lastMessageDate))}">${esc7(when(c.lastMessageDate))}</time></header><p>${esc7(c.lastMessageBody || "Sin vista previa")}</p><footer><span class="channel">${channel(c.lastMessageType)}</span>${recordatorioDe(c.id) ? `<span class="rec-chip" title="Recordatorio de seguimiento">${esc7(cuandoTexto(recordatorioDe(c.id).at))}</span>` : ""}${filter === "waiting" && stamp(c) ? `<span class="waiting${Date.now() - stamp(c) > 36e5 ? " late" : ""}">Espera ${waited(c)}</span>` : ""}${Number(c.unreadCount) > 0 ? `<b class="unread">${Number(c.unreadCount)}<span class="sr-only"> pendientes</span></b>` : ""}</footer></span></button>`).join("") || (listLoading ? skeleton("list", 7) : '<p class="empty">No hay conversaciones con este filtro.</p>');
        if (hasMore) $("#live-list").insertAdjacentHTML("beforeend", `<button id="live-more" class="live-more"${loadingMore ? " disabled" : ""}>${loadingMore ? "Cargando…" : "Cargar más conversaciones"}</button>`);
        container.querySelectorAll("[data-live-id]").forEach((b) => b.onclick = () => open(conversations.find((c) => c.id === b.dataset.liveId)));
        const more = $("#live-more");
        if (more) more.onclick = () => page_more();
      }, renderMessages = function() {
        const log = $("#live-log");
        queueMicrotask(() => {
          const hint = $("#live-window-hint");
          if (hint) hint.hidden = !(chatChannel === "WhatsApp" && windowClosed());
        });
        log.innerHTML = [...messages.values()].sort((a, b) => (Date.parse(a.dateAdded) || 0) - (Date.parse(b.dateAdded) || 0)).map((m) => {
          const files = attachmentsOf(m);
          const body = m.body ? esc7(m.body) : files.length ? "" : "Mensaje sin texto";
          const failed = ["failed", "undelivered", "opt_out"].includes(m.status);
          const dm = { TYPE_INSTAGRAM_COMMENT: "IG", TYPE_FACEBOOK_COMMENT: "FB" }[m.messageType];
          const tag = commentTypes[m.messageType] ? `<span class="comment-tag">Comentario en una publicación de ${commentTypes[m.messageType]}</span>` : "";
          const dmButton = dm && m.direction !== "outbound" ? `<button type="button" class="reply-dm" data-dm="${dm}">Responder como DM</button>` : "";
          return `<div class="bubble ${m.direction === "outbound" ? "out" : ""}${failed ? " failed" : ""}" data-mid="${esc7(m.id)}">${tag}${body}${dmButton}${files.length ? `<div class="live-attachments">${files.map(attachmentMarkup).join("")}</div>` : ""}${statusMarkup(m, date(m.dateAdded))}</div>`;
        }).join("") || '<p class="empty">No hay mensajes disponibles.</p>';
        bindAttachments(log);
        log.querySelectorAll("[data-dm]").forEach((button) => button.onclick = () => {
          const row = $("#live-channel-row"), target = row?.querySelector(`[data-channel="${button.dataset.dm}"]`), notice = $("#live-send-status");
          const already = chatChannel === button.dataset.dm;
          if (!already && (!target || target.disabled)) {
            if (notice) notice.textContent = "Este contacto todavía no se puede responder por mensaje directo.";
            return;
          }
          if (!already) target.click();
          const field = $("#live-reply");
          if (field) {
            field.scrollIntoView({ block: "center", behavior: calm() ? "auto" : "smooth" });
            field.focus();
          }
          if ($("#live-send-status")) $("#live-send-status").textContent = "Respondes por mensaje directo, no en el comentario.";
        });
        if (messages.size && !log.dataset.painted) setTimeout(() => {
          log.dataset.painted = "1";
        }, 1200);
        const ordered = [...messages.values()].sort((a, b) => (Date.parse(a.dateAdded) || 0) - (Date.parse(b.dateAdded) || 0));
        const firstComment = ordered.length && commentTypes[ordered[0].messageType] ? ordered[0] : null;
        const anyComment = firstComment || ordered.find((m) => commentTypes[m.messageType]);
        if (anyComment && log.innerHTML.indexOf('class="empty"') < 0) {
          const net = commentTypes[anyComment.messageType];
          log.insertAdjacentHTML("afterbegin", `<p class="origin-note">${firstComment ? `Su primera interacción fue un comentario en una publicación de ${net}.` : `Este contacto también comentó en una publicación de ${net}.`} Tu respuesta se envía como mensaje directo.</p>`);
        }
        log.querySelectorAll("[data-retry]").forEach((button) => button.onclick = () => {
          const original = messages.get(button.dataset.retry);
          const field = $("#live-reply");
          if (!original || !field) return;
          field.value = original.body;
          field.dispatchEvent(new Event("input", { bubbles: true }));
          field.focus();
          const notice = $("#live-send-status");
          if (notice) notice.textContent = "Revisa el texto, elige el canal y pulsa Enviar.";
        });
      }, effectiveChatDelay = function() {
        return Math.max(CHAT_FALLBACK, pause - Date.now() / 1e3);
      }, composer = function(c, channels = null) {
        const offered = channels ?? threadChannel(c);
        const usable = usableChannels(offered);
        if (!usable.length || !c.contactId) return '<p class="live-readonly">Este canal todavía no admite respuestas desde aquí.</p>';
        const chosen = usable.find((o) => o.type === chatChannel) || usable[0];
        chatChannel = chosen.type;
        return `<form id="live-composer" class="live-composer"><div class="live-channels" id="live-channel-row" role="group" aria-label="Canal de envío">${pickerMarkup(offered)}</div><div id="live-subject-slot">${chosen.needsSubject ? '<label class="live-subject-field">Asunto<input id="live-subject" maxlength="200" required></label>' : ""}</div><p id="live-window-hint" class="live-window-hint" hidden>Han pasado más de 24 horas desde su último mensaje. WhatsApp sólo permite escribir con una plantilla aprobada.</p><div id="live-template-slot"></div><label class="sr-only" for="live-reply">Respuesta</label><textarea id="live-reply" maxlength="4000" rows="3" required placeholder="Escribe una respuesta…"></textarea><div class="live-composer-actions"><span id="live-channel-name">${esc7(chosen.label)}</span><button type="button" id="live-template-toggle" class="link-button" hidden>Usar plantilla</button><button id="live-send" class="primary">Enviar</button></div><p id="live-send-status" role="status"></p></form>`;
      }, applyChannels = function(c, offered, draft) {
        const row = $("#live-channel-row");
        if (!row) return;
        const usable = usableChannels(offered);
        const chosen = usable.find((o) => o.type === chatChannel) || usable[0];
        if (!chosen) return;
        chatChannel = chosen.type;
        row.innerHTML = pickerMarkup(offered);
        row.classList.toggle("live-channels-single", asList6(offered).length < 2);
        const name2 = $("#live-channel-name");
        if (name2) name2.textContent = chosen.label;
        syncTemplates(c);
        const slot = $("#live-subject-slot");
        if (slot) {
          const present = !!$("#live-subject");
          if (chosen.needsSubject && !present) slot.innerHTML = '<label class="live-subject-field">Asunto<input id="live-subject" maxlength="200" required></label>';
          if (!chosen.needsSubject && present) slot.innerHTML = "";
          const topic = $("#live-subject");
          if (topic) {
            topic.value = draft.subject;
            topic.disabled = draft.sending;
            topic.oninput = () => draft.subject = topic.value;
          }
        }
        const current2 = row.querySelector(".channel-current"), menu = row.querySelector(".channel-list");
        if (current2 && menu) current2.onclick = (e) => {
          e.stopPropagation();
          const open2 = menu.hidden;
          menu.hidden = !open2;
          current2.setAttribute("aria-expanded", String(open2));
        };
        row.querySelectorAll("[data-channel]:not([disabled])").forEach((button) => button.onclick = () => {
          if (draft.sending || button.dataset.channel === chatChannel) return;
          chatChannel = button.dataset.channel;
          draft.notice = "";
          applyChannels(c, offered, draft);
          const notice = $("#live-send-status");
          if (notice) notice.textContent = "";
        });
      }, cuandoTexto = function(iso) {
        const d = new Date(iso);
        if (Number.isNaN(d.getTime())) return "";
        const hoy = /* @__PURE__ */ new Date();
        hoy.setHours(0, 0, 0, 0);
        const dia = new Date(d);
        dia.setHours(0, 0, 0, 0);
        const faltan = Math.round((dia - hoy) / 864e5);
        const hora = new Intl.DateTimeFormat("es-VE", { hour: "2-digit", minute: "2-digit" }).format(d);
        if (faltan === 0) return `hoy ${hora}`;
        if (faltan === 1) return `mañana ${hora}`;
        if (faltan === -1) return `ayer ${hora}`;
        if (faltan < 0) return `vencido · ${new Intl.DateTimeFormat("es-VE", { day: "numeric", month: "short" }).format(d)}`;
        return `${new Intl.DateTimeFormat("es-VE", { day: "numeric", month: "short" }).format(d)} ${hora}`;
      }, recordatorioDialog = function(c) {
        const dialog2 = document.querySelector("#action-dialog"), content = document.querySelector("#dialog-content");
        const previo = recordatorioDe(c.id);
        const base2 = previo ? new Date(previo.at) : new Date(Date.now() + 864e5);
        const ymd2 = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}-${String(d.getDate()).padStart(2, "0")}`;
        const hm = (d) => `${String(d.getHours()).padStart(2, "0")}:${String(d.getMinutes()).padStart(2, "0")}`;
        const atajo = (etiqueta, dias, hora) => `<button type="button" class="secondary" data-rec-atajo="${dias}|${hora}">${etiqueta}</button>`;
        content.innerHTML = `<h2>Recordatorio de seguimiento</h2>
    <p class="dialog-copy">Elige cuándo le vuelves a escribir a <b>${esc7(name(c))}</b>. Ese día te queda la tarea y esta conversación se pone de primera en la bandeja hasta que mandes el mensaje.</p>
    <form id="rec-form" class="dialog-form">
     <div class="rec-atajos">${atajo("Esta tarde", 0, "16:00")}${atajo("Mañana", 1, "09:00")}${atajo("En 3 días", 3, "09:00")}${atajo("La semana que viene", 7, "09:00")}</div>
     <div class="announce-grid">
      <label>Día<input type="date" name="dia" required value="${esc7(ymd2(base2))}" min="${esc7(ymd2(/* @__PURE__ */ new Date()))}"></label>
      <label>Hora<input type="time" name="hora" required value="${esc7(hm(base2))}"></label>
     </div>
     <label>Nota para ti (opcional)<input name="nota" maxlength="140" value="${esc7(previo?.nota || "")}" placeholder="Quedó en confirmar con su esposo"></label>
     <div class="dialog-actions"><button class="primary">${previo ? "Guardar el recordatorio" : "Poner el recordatorio"}</button>${previo ? '<button type="button" class="danger" id="rec-quitar">Quitar</button>' : ""}</div>
     <p id="rec-result" class="dialog-copy" role="status"></p>
    </form>`;
        if (!dialog2.open) dialog2.showModal();
        const form = content.querySelector("#rec-form");
        content.querySelectorAll("[data-rec-atajo]").forEach((b) => b.onclick = () => {
          const [dias, hora] = b.dataset.recAtajo.split("|");
          const d = /* @__PURE__ */ new Date();
          d.setDate(d.getDate() + Number(dias));
          form.elements.dia.value = ymd2(d);
          form.elements.hora.value = hora;
        });
        const quitar = content.querySelector("#rec-quitar");
        if (quitar) quitar.onclick = async () => {
          quitar.disabled = true;
          try {
            await request2(`workspace/reminders?connectionId=${encodeURIComponent(select.value)}&conversationId=${encodeURIComponent(c.id)}`, "DELETE", me?.csrf || "");
            recordatorios.delete(c.id);
            dialog2.close();
            list();
            pintarRecordatorio(c);
            window.dispatchEvent(new CustomEvent("agora:toast", { detail: "Recordatorio quitado." }));
          } catch (error) {
            quitar.disabled = false;
            content.querySelector("#rec-result").textContent = friendly(error);
          }
        };
        form.onsubmit = async (event) => {
          event.preventDefault();
          const boton = form.querySelector("button.primary"), salida = content.querySelector("#rec-result");
          const at = `${form.elements.dia.value}T${form.elements.hora.value}`;
          if (new Date(at).getTime() < Date.now() - 6e4) {
            salida.textContent = "Elige un momento que todavía no haya pasado.";
            return;
          }
          boton.disabled = true;
          salida.textContent = "Guardando…";
          try {
            const guardado = await request2(
              `workspace/reminders?connectionId=${encodeURIComponent(select.value)}&conversationId=${encodeURIComponent(c.id)}`,
              "POST",
              me?.csrf || "",
              { at, nota: form.elements.nota.value.trim(), contactId: c.contactId || null, contactName: name(c) }
            );
            recordatorios.set(c.id, { at: guardado?.at || at, nota: form.elements.nota.value.trim() });
            dialog2.close();
            list();
            pintarRecordatorio(c);
            window.dispatchEvent(new CustomEvent("agora:reminder", { detail: { conversationId: c.id, cuando: cuandoTexto(at), contacto: name(c) } }));
          } catch (error) {
            boton.disabled = false;
            salida.textContent = friendly(error);
          }
        };
      }, pintarRecordatorio = function(c) {
        const barra = $("#live-rec");
        if (!barra) return;
        const r = recordatorioDe(c.id);
        const boton = $("#live-recordar");
        if (boton) boton.classList.toggle("on", !!r);
        if (!r) {
          barra.hidden = true;
          barra.innerHTML = "";
          return;
        }
        barra.hidden = false;
        barra.innerHTML = `<span class="rec-pin" aria-hidden="true"></span><span>Recordatorio para <b>${esc7(cuandoTexto(r.at))}</b>${r.nota ? ` · ${esc7(r.nota)}` : ""}</span><button type="button" id="rec-editar" class="link-button">Cambiar</button>`;
        barra.querySelector("#rec-editar").onclick = () => recordatorioDialog(c);
      }, assignDialog = function(c, assignees, current2) {
        const dialog2 = document.querySelector("#action-dialog"), content = document.querySelector("#dialog-content");
        const connection = select.value, version = chatGeneration;
        content.innerHTML = `<h2>Asignar la conversación</h2>
    <p class="dialog-copy">La persona que elijas pasa a ser <b>responsable del contacto</b> y de sus <b>oportunidades abiertas</b>, y lo que cierre contará en sus ventas.</p>
    <form id="assign-form" class="dialog-form">
     <label>Responsable<select name="assignedTo"><option value="">Sin asignar</option>${asList6(assignees).map((user) => `<option value="${esc7(user.id)}"${user.id === current2 ? " selected" : ""}>${esc7(user.name)}</option>`).join("")}</select></label>
     <div class="dialog-actions"><button class="primary">Asignar</button></div>
     <p id="assign-result" class="dialog-copy" role="status"></p>
    </form>`;
        if (!dialog2.open) dialog2.showModal();
        const form = content.querySelector("#assign-form"), result = content.querySelector("#assign-result");
        form.onsubmit = async (e) => {
          e.preventDefault();
          const target = form.elements.assignedTo.value;
          if (target === (current2 || "")) {
            result.textContent = "Esa persona ya es la responsable.";
            return;
          }
          const digest = await crypto.subtle.digest("SHA-256", new TextEncoder().encode(connection + c.id + target));
          const storageKey = "agora-assign:" + Array.from(new Uint8Array(digest), (b) => b.toString(16).padStart(2, "0")).join("");
          const requestId = sessionStorage.getItem(storageKey) || crypto.randomUUID().replace(/-/g, "");
          sessionStorage.setItem(storageKey, requestId);
          const button = form.querySelector("button.primary");
          button.disabled = true;
          result.textContent = "Asignando…";
          try {
            const done = await request2(`workspace/assign?connectionId=${encodeURIComponent(connection)}&conversationId=${encodeURIComponent(c.id)}`, "POST", me.csrf, { assignedTo: target, requestId });
            sessionStorage.removeItem(storageKey);
            c.assignedTo = done.assignedTo || void 0;
            dialog2.close();
            const moved = done.opportunities?.updated || 0;
            window.dispatchEvent(new CustomEvent("agora:toast", { detail: done.assignedName ? `Asignada a ${done.assignedName}${moved ? ` · ${moved} oportunidad${moved === 1 ? "" : "es"} abierta${moved === 1 ? "" : "s"} también` : ""}.` : "La conversación quedó sin asignar." }));
            if (active() && version === chatGeneration) {
              list();
              loadContact(c, chatGeneration);
            }
          } catch (error) {
            if (["assign_forbidden", "unknown_assignee", "invalid_contact"].includes(error.code)) sessionStorage.removeItem(storageKey);
            result.textContent = friendly(error);
            button.disabled = false;
          }
        };
      }, windowClosed = function() {
        let last = 0;
        for (const m of messages.values()) if (m.direction === "inbound") {
          const t = Date.parse(m.dateAdded);
          if (t > last) last = t;
        }
        return last > 0 && Date.now() - last > 24 * 3600 * 1e3;
      }, chosenTemplate = function() {
        return (templates.items || []).find((t) => t.id === templates.chosen) || null;
      }, templateIncomplete = function(t) {
        return ["header", "body"].some((part) => (t.variables[part] || []).some((v) => v.fixed === null && !String((templates.values[part] || {})[v.token] || "").trim()));
      }, templatePreview = function(t) {
        const fill = (text, part) => esc7(text).replace(/\{\{\s*([A-Za-z0-9_.]{1,60})\s*\}\}/g, (all, token) => {
          const v = (t.variables[part] || []).find((x) => x.token === token);
          if (/^\d+$/.test(token) && v && v.fixed === null) {
            const typed = (templates.values[part] || {})[token];
            return typed ? `<mark>${esc7(typed)}</mark>` : `<mark class="empty">${esc7(v.label)}</mark>`;
          }
          const field = v && v.fixed ? v.fixed.replace(/^\{\{\s*|\s*\}\}$/g, "") : token;
          return `<mark class="field">${esc7(FIELD_NAMES[field] || field)}</mark>`;
        });
        if (t.preview === false) return `<p class="muted">La cuenta no envía el texto de esta plantilla, así que no hay vista previa. Se enviará «${esc7(t.name.replace(/_/g, " "))}» tal como está aprobada.</p>`;
        return `${t.header ? `<strong>${fill(t.header, "header")}</strong>` : ""}<p>${fill(t.body, "body").replace(/\n/g, "<br>")}</p>${t.footer ? `<small>${esc7(t.footer)}</small>` : ""}${t.buttons.length ? `<div class="tpl-buttons">${t.buttons.map((b) => `<span>${esc7(b)}</span>`).join("")}</div>` : ""}`;
      }, templateMarkup = function() {
        if (!templates.open) return "";
        const close = '<button type="button" class="link-button" id="tpl-close">Volver a escribir</button>';
        if (templates.items === null) return `<div class="tpl-panel"><p class="muted">Cargando plantillas…</p>${close}</div>`;
        if (templates.error) return `<div class="tpl-panel"><p class="muted">${esc7(templates.error)}</p>${close}</div>`;
        if (!templates.configured) return `<div class="tpl-panel"><p class="muted">Esta cuenta todavía no tiene el número de WhatsApp para plantillas. El administrador lo añade en Conexiones.</p>${close}</div>`;
        if (!templates.items.length) return `<div class="tpl-panel"><p class="muted">La cuenta no devolvió plantillas aprobadas para este número. El administrador puede revisarlo en Conexiones → Plantillas de WhatsApp → Comprobar plantillas.</p>${close}</div>`;
        const t = chosenTemplate();
        const free = t ? ["header", "body"].flatMap((part) => (t.variables[part] || []).filter((v) => v.fixed === null).map((v) => ({ part, ...v }))) : [];
        return `<div class="tpl-panel"><label class="tpl-pick">Plantilla aprobada<select id="tpl-select"><option value="">Elige una plantilla</option>${templates.items.map((x) => `<option value="${esc7(x.id)}"${x.id === templates.chosen ? " selected" : ""}>${esc7(x.name.replace(/_/g, " "))}${x.category ? " · " + esc7(CATEGORY[x.category] || x.category) : ""}</option>`).join("")}</select></label>
    ${t ? `<div class="tpl-preview">${templatePreview(t)}</div>${t.unsupported ? '<p class="muted">Esta plantilla lleva una imagen o un enlace que cambia. Por ahora envíala desde la cuenta.</p>' : ""}
    ${free.map((v) => `<label class="tpl-var">${esc7(v.label)}<input data-tpl-part="${v.part}" data-tpl-token="${esc7(v.token)}" maxlength="200" value="${esc7((templates.values[v.part] || {})[v.token] || "")}"></label>`).join("")}
    <p class="muted tpl-note">Los datos marcados en gris, como el nombre, los completa la cuenta con la ficha del contacto.</p>` : ""}
    <div class="tpl-actions">${close}<button type="button" class="primary" id="tpl-send"${!t || t.unsupported || templates.sending || templateIncomplete(t) ? " disabled" : ""}>${templates.sending ? "Enviando…" : "Enviar plantilla"}</button></div>
    ${templates.notice ? `<p class="muted" role="status">${esc7(templates.notice)}</p>` : ""}</div>`;
      }, syncTemplates = function(c) {
        if (templates.generation !== chatGeneration) {
          templates = { open: false, items: null, configured: true, error: "", chosen: "", values: {}, sending: false, notice: "", generation: chatGeneration };
        }
        const form = $("#live-composer");
        if (!form) return;
        const whatsapp = chatChannel === "WhatsApp";
        if (!whatsapp) templates.open = false;
        const toggle = $("#live-template-toggle");
        if (toggle) toggle.hidden = !whatsapp || templates.open;
        const hint = $("#live-window-hint");
        if (hint) hint.hidden = !(whatsapp && windowClosed());
        form.classList.toggle("template-mode", templates.open);
        const slot = $("#live-template-slot");
        if (!slot) return;
        slot.innerHTML = templateMarkup();
        const back = $("#tpl-close");
        if (back) back.onclick = () => {
          templates.open = false;
          templates.notice = "";
          syncTemplates(c);
        };
        const pick = $("#tpl-select");
        if (pick) pick.onchange = () => {
          templates.chosen = pick.value;
          templates.values = {};
          templates.notice = "";
          syncTemplates(c);
        };
        slot.querySelectorAll("[data-tpl-token]").forEach((input) => input.oninput = () => {
          (templates.values[input.dataset.tplPart] ??= {})[input.dataset.tplToken] = input.value;
          const t = chosenTemplate(), box = slot.querySelector(".tpl-preview");
          if (t && box) box.innerHTML = templatePreview(t);
          const go = $("#tpl-send");
          if (go && t) go.disabled = !!t.unsupported || templates.sending || templateIncomplete(t);
        });
        const send = $("#tpl-send");
        if (send) send.onclick = () => sendTemplate(c);
      }, bindComposer = function(c) {
        const form = $("#live-composer");
        if (!form) return;
        const connection = select.value, version = chatGeneration, key = connection + ":" + c.id;
        const draft = drafts.get(key) || { message: "", subject: "", sending: false, notice: "" };
        drafts.set(key, draft);
        applyChannels(c, chatChannels ?? threadChannel(c), draft);
        const text = $("#live-reply"), subject = $("#live-subject");
        const show2 = () => {
          const field = $("#live-reply"), topic = $("#live-subject"), send = $("#live-send"), notice = $("#live-send-status");
          if (!field) return;
          field.value = draft.message;
          field.disabled = draft.sending;
          if (topic) {
            topic.value = draft.subject;
            topic.disabled = draft.sending;
          }
          if (send) {
            send.disabled = draft.sending;
            send.textContent = draft.sending ? "Enviando…" : "Enviar";
          }
          if (notice) notice.textContent = draft.notice;
        };
        show2();
        text.oninput = () => draft.message = text.value;
        if (subject) subject.oninput = () => draft.subject = subject.value;
        form.onsubmit = async (e) => {
          e.preventDefault();
          if (draft.sending || !draft.message.trim()) return;
          draft.sending = true;
          draft.notice = "Enviando…";
          show2();
          let storageKey;
          const body = { channel: chatChannel || sendChannels[c.lastMessageType], message: draft.message.trim(), subject: draft.subject.trim() };
          try {
            const digest = await crypto.subtle.digest("SHA-256", new TextEncoder().encode(JSON.stringify(body)));
            storageKey = "agora-send:" + key + ":" + Array.from(new Uint8Array(digest), (b) => b.toString(16).padStart(2, "0")).join("");
            const requestId = sessionStorage.getItem(storageKey) || crypto.randomUUID();
            sessionStorage.setItem(storageKey, requestId);
            const result = await request2(`workspace/send?connectionId=${encodeURIComponent(connection)}&conversationId=${encodeURIComponent(c.id)}`, "POST", me.csrf, { ...body, requestId });
            sessionStorage.removeItem(storageKey);
            draft.message = "";
            draft.notice = result.deliveryStatus === "failed" ? "El canal registró el mensaje como fallido. Revisa su configuración." : "Mensaje aceptado por el canal.";
            if (active() && version === chatGeneration) {
              messages.set(result.messageId, { id: result.messageId, body: body.message, direction: "outbound", dateAdded: (/* @__PURE__ */ new Date()).toISOString(), status: result.deliveryStatus });
              renderMessages();
              scrollEnd($("#live-log"), true);
              c.lastMessageBody = body.message;
              list();
            }
          } catch (error) {
            draft.notice = friendly(error);
            if (storageKey && ["send_rejected", "invalid_message", "invalid_subject", "unsupported_channel", "invalid_contact", "connection_unverified"].includes(error.code)) sessionStorage.removeItem(storageKey);
          } finally {
            draft.sending = false;
            if (active() && version === chatGeneration) show2();
          }
        };
        const toggle = $("#live-template-toggle");
        if (toggle) toggle.onclick = () => {
          templates.open = true;
          templates.notice = "";
          if (templates.items === null || templates.error) loadTemplates(c);
          else syncTemplates(c);
        };
        syncTemplates(c);
      }, opportunityCard = function(item, canEdit = false) {
        const rows2 = [["Pipeline", item.pipelineName], ["Etapa", item.stageName], ["Estado", item.statusLabel], ["Valor", amount2(item.monetaryValue)], ["Responsable", item.assignedName], ["Fuente", item.source]].filter(([, value]) => value);
        return `<div class="live-opportunity"><h3>${esc7(item.name || "Oportunidad sin nombre")}</h3>${rows2.map(([label, value]) => `<div><span>${esc7(label)}</span><strong>${esc7(value)}</strong></div>`).join("")}${canEdit ? `<button class="live-opportunity-edit" data-edit-opportunity="${esc7(item.id)}">Editar oportunidad</button>` : ""}</div>`;
      }, opportunitySection = function(opportunities, editor = {}) {
        if (!opportunities) return '<span class="muted">Cargando oportunidades…</span>';
        if (opportunities.error) return `<span class="muted">${esc7(opportunities.error)}</span>`;
        const items2 = asList6(opportunities.items);
        const canEdit = opportunities.canEdit === true;
        if (editor.open) return (editor.pipelines ? opportunityForm({ pipelines: editor.pipelines.pipelines, assignees: editor.pipelines.assignees, opportunity: editor.open === "new" ? null : items2.find((item) => item.id === editor.open) || null }) : '<span class="muted">Cargando embudos…</span>') + (editor.notice ? `<span class="muted">${esc7(editor.notice)}</span>` : "");
        const add = canEdit ? '<button class="live-opportunity-new" data-new-opportunity>+ Nueva oportunidad</button>' : "";
        const notice = editor.notice ? `<span class="muted">${esc7(editor.notice)}</span>` : "";
        if (!items2.length || !opportunities.active) return `<span class="muted">${opportunities.restricted ? "Sin oportunidades asignadas a tu usuario." : "Este contacto no tiene oportunidades."}</span>${add}${notice}`;
        const others = items2.filter((item) => item.id !== opportunities.active.id);
        return opportunityCard(opportunities.active, canEdit) + (others.length ? `<details class="live-opportunity-all"><summary>Ver todas las oportunidades (${items2.length})</summary>${others.map((item) => opportunityCard(item, canEdit)).join("")}</details>` : "") + (opportunities.partial ? '<span class="muted">Se muestran las oportunidades más recientes de este contacto.</span>' : "") + add + notice;
      }, activitySection = function(activity) {
        if (!activity) return '<span class="muted">Cargando actividad…</span>';
        if (activity.error) return `<span class="muted">${esc7(activity.error)}</span>`;
        activity = { ...activity, tasks: asList6(activity.tasks), notes: asList6(activity.notes), appointments: asList6(activity.appointments) };
        const tasks = activity.tasks.length ? activity.tasks.map((task) => `<div class="live-task${task.completed ? " done" : ""}"><button data-task="${esc7(task.id)}" data-completed="${task.completed ? 1 : 0}"${activity.canEdit ? "" : " disabled"} aria-label="${task.completed ? "Reabrir tarea" : "Completar tarea"}">${task.completed ? "☑" : "☐"}</button><div><strong>${esc7(task.title)}</strong>${task.body ? `<p>${esc7(task.body)}</p>` : ""}<small>${esc7([dayOnly(task.dueDate), task.assignedName].filter(Boolean).join(" · ") || "Sin fecha")}</small></div></div>`).join("") : '<span class="muted">Sin tareas para este contacto.</span>';
        const notes = activity.notes.length ? activity.notes.map((note) => `<div class="live-note"><p>${esc7(note.body)}</p><small>${esc7([date(note.dateAdded), note.authorName].filter(Boolean).join(" · "))}</small></div>`).join("") : '<span class="muted">Sin notas para este contacto.</span>';
        const appointments = !activity.appointmentsVisible ? '<span class="muted">Tu usuario no tiene el módulo de citas activo.</span>' : activity.appointments.length ? activity.appointments.map((item) => `<div class="live-appointment"><strong>${esc7(item.title)}</strong><small>${esc7([date(item.startTime), item.statusLabel, item.assignedName, item.address].filter(Boolean).join(" · "))}</small></div>`).join("") : '<span class="muted">Sin citas para este contacto.</span>';
        const forms = activity.canEdit ? `<div class="live-quick-actions"><details><summary>+ Crear tarea</summary><form id="live-task-form"><label class="sr-only" for="live-task-title">Título de la tarea</label><input id="live-task-title" maxlength="190" placeholder="Título de la tarea" required><label class="sr-only" for="live-task-due">Vencimiento</label><input id="live-task-due" type="date" required><label class="sr-only" for="live-task-body">Detalle</label><textarea id="live-task-body" maxlength="2000" rows="2" placeholder="Detalle (opcional)"></textarea><button class="primary">Crear tarea</button></form></details><details><summary>+ Crear nota</summary><form id="live-note-form"><label class="sr-only" for="live-note-body">Nota</label><textarea id="live-note-body" maxlength="5000" rows="3" placeholder="Escribe una nota…" required></textarea><button class="primary">Guardar nota</button></form></details><p id="live-activity-status" role="status"></p></div>` : '<p id="live-activity-status" role="status"></p>';
        return `<div class="section-label">Tareas</div><div class="live-tasks">${tasks}</div><div class="section-label">Notas</div><div class="live-notes">${notes}</div><div class="section-label">Citas</div><div class="live-appointments">${appointments}</div>${forms}`;
      }, contactProfile = function(profile2, labels = {}, opportunities = null, activity = null, editor = {}) {
        const details = [["Teléfono", profile2.phone], ["Email", profile2.email], ["Empresa", profile2.companyName], ["Sitio web", profile2.website], ["Fuente", profile2.source], ["Responsable", profile2.assignedName], ["Última actividad", date(profile2.lastActivity)], ["Alta", date(profile2.dateAdded)], ["Nacimiento", profile2.dateOfBirth], ["Zona horaria", profile2.timezone], ["Dirección", profile2.address], ["No contactar", profile2.dnd ? "Sí" : null]].filter(([, value]) => value);
        const phone = String(profile2.phone || "").replace(/[^0-9+]/g, "");
        return `<header class="live-profile-header"><div><span class="section-label">Contexto del contacto</span><h2>${esc7(profile2.name)}</h2></div><div class="live-profile-controls">${profile2.canEdit ? '<button id="live-edit-contact">Editar</button>' : ""}<button id="live-profile-close" aria-label="Cerrar ficha">×</button></div></header>${profile2.email ? `<div class="live-profile-actions"><a href="mailto:${encodeURIComponent(profile2.email)}">Escribir por correo</a></div>` : ""}<div class="live-profile-details">${details.map(([label, value]) => `<div><span>${esc7(label)}</span><strong>${esc7(value)}</strong></div>`).join("")}</div><div class="section-label">Etiquetas</div><div class="live-profile-tags">${profile2.tags?.length ? profile2.tags.map((tag) => `<span class="tag">${esc7(tag)}</span>`).join("") : '<span class="muted">Sin etiquetas</span>'}</div><div class="section-label">Campos personalizados</div><div class="live-profile-fields">${(() => {
          const filled2 = asList6(profile2.customFields).filter((field) => field.value !== "" && field.value != null);
          return filled2.length ? groupByFolder(filled2, editor.directory ? editor.directory() : []).map((g) => `<div class="folder-title">${esc7(g.title)}</div>` + g.items.map((field) => `<div><span>${esc7(labels[field.id] || field.label)}</span><strong>${esc7(field.value)}</strong></div>`).join("")).join("") : '<span class="muted">Sin campos con valor</span>';
        })()}</div><div class="section-label">Oportunidad activa</div><div class="live-profile-opportunities">${opportunitySection(opportunities, editor)}</div><div class="live-profile-activity">${activitySection(activity)}</div>`;
      }, open = function(c) {
        selected = c;
        chatNeedsRefresh = false;
        chatGeneration++;
        messages = /* @__PURE__ */ new Map();
        cursor = null;
        chatChannel = sendChannels[c.lastMessageType] || null;
        chatChannels = null;
        list();
        layout.classList.add("chat-open");
        layout.classList.remove("contact-open");
        $("#live-messages").innerHTML = `<header class="live-chat-header"><button id="live-back" aria-label="Volver a conversaciones">${UI_ICONS.back}</button><span class="avatar">${esc7(initials(c))}</span><div><h2>${esc7(name(c))}</h2><p>${channel(c.lastMessageType)}</p></div>${canWin() ? '<button id="live-won" class="won-button" title="¿Venta ganada?" aria-label="¿Venta ganada?"><span aria-hidden="true">✓</span><span class="won-long">¿Venta ganada?</span><span class="won-short">¿Ganada?</span><span class="sr-only">¿Venta ganada?</span></button>' : ""}<button id="live-recordar" class="rec-button" title="Recordatorio de seguimiento" aria-label="Recordatorio de seguimiento"><span aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="ui-icon"><circle cx="12" cy="13" r="8"/><path d="M12 9.5V13l2.5 1.5M9 2.5h6"/></svg></span><span class="rec-text">Recordar</span></button><button id="live-assign" class="assign-button" hidden title="Asignar la conversación" aria-label="Asignar la conversación"><span aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="ui-icon"><circle cx="9" cy="8" r="3.6"/><path d="M2.5 20c.7-3.6 3.2-5.5 6.5-5.5s5.8 1.9 6.5 5.5"/><path d="M19 8v6M16 11h6"/></svg></span><span class="assign-text">Asignar</span></button><button id="live-focus" aria-label="Ampliar la conversación" title="Ampliar la conversación">${UI_ICONS.expand}</button><button id="live-contact-toggle" aria-label="Ver ficha del contacto" title="Ver ficha del contacto">${UI_ICONS.contact}</button></header><div id="live-rec" class="live-rec" role="status" hidden></div><div class="live-history-actions"><button id="live-older" hidden>Cargar mensajes anteriores</button><p id="live-chat-status" role="status"></p><button id="live-retry" hidden>Reintentar</button></div><div id="live-log" class="messages live-message-log" aria-label="Historial de mensajes"></div>${composer(c)}`;
        {
          const log = $("#live-log");
          let stick = true;
          log.addEventListener("scroll", () => {
            stick = log.scrollHeight - log.scrollTop - log.clientHeight < 80;
          }, { passive: true });
          if ("ResizeObserver" in window) new ResizeObserver(() => {
            if (stick && messages.size) log.scrollTop = log.scrollHeight;
          }).observe(log);
        }
        $("#live-back").onclick = () => {
          clearTimeout(pollTimer);
          document.body.classList.remove("agora-focus");
          layout.classList.remove("chat-open", "contact-open");
          say();
          $("#live-search").focus();
        };
        $("#live-contact-toggle").onclick = () => layout.classList.toggle("contact-open");
        const recordar = $("#live-recordar");
        if (recordar) recordar.onclick = () => recordatorioDialog(c);
        pintarRecordatorio(c);
        const won = $("#live-won");
        if (won) won.onclick = () => winDialog(c);
        if (Number(c.unreadCount) > 0 && me?.csrf) {
          request2(`workspace/read?connectionId=${encodeURIComponent(select.value)}&conversationId=${encodeURIComponent(c.id)}`, "POST", me.csrf).then(() => {
            c.unreadCount = 0;
            if (active()) list();
            window.dispatchEvent(new CustomEvent("agora:unread", { detail: conversations.reduce((sum, item) => sum + (Number(item.unreadCount) || 0), 0) }));
          }).catch(() => {
          });
        }
        $("#live-older").onclick = () => page(true);
        const focus = $("#live-focus");
        const paintFocus = () => {
          const on = document.body.classList.contains("agora-focus");
          focus.innerHTML = on ? UI_ICONS.shrink : UI_ICONS.expand;
          focus.classList.toggle("active", on);
          focus.setAttribute("aria-label", on ? "Reducir la conversación" : "Ampliar la conversación");
          focus.title = focus.getAttribute("aria-label");
        };
        document.body.classList.toggle("agora-focus", focusWanted);
        focus.onclick = () => {
          focusWanted = !document.body.classList.contains("agora-focus");
          document.body.classList.toggle("agora-focus", focusWanted);
          try {
            localStorage.setItem("agora-focus", focusWanted ? "1" : "0");
          } catch {
          }
          paintFocus();
        };
        paintFocus();
        bindComposer(c);
        const profileVersion = chatGeneration;
        page().finally(() => {
          if (active() && profileVersion === chatGeneration) loadContact(c, profileVersion);
        });
        say();
        scheduleChat();
        schedulePulse(2);
      }, paintChannelFilters = function() {
        const row = $("#live-channel-filters");
        if (!row) return;
        const entries = Object.entries(channelNames);
        if (!entries.length) {
          row.innerHTML = "";
          return;
        }
        row.innerHTML = `<select id="live-channel-select" aria-label="Filtrar por canal" class="${channelFilter ? "filtered" : ""}"><option value="">Todos los canales</option>${entries.map(([type, label]) => `<option value="${esc7(type)}"${channelFilter === type ? " selected" : ""}>${esc7(label)}</option>`).join("")}</select>`;
        $("#live-channel-select").onchange = (e) => {
          const value = e.target.value;
          if (value === channelFilter) return;
          channelFilter = value;
          paintChannelFilters();
          load();
        };
      };
      const me = await request2("me");
      const { items } = await request2("workspace/connections");
      if (!active()) return;
      container.innerHTML = `<div class="live-toolbar"><p id="live-feedback" class="live-feedback" role="status"></p><label>Mostrar<select id="live-limit" aria-label="Cantidad de conversaciones"><option value="20">20</option><option value="50">50</option><option value="100">100</option></select></label></div><p id="live-auto-status" class="live-auto-status" role="status" hidden></p><div class="live-inbox-layout"><section class="live-sidebar" aria-label="Lista de conversaciones"><label class="search"><span aria-hidden="true">⌕</span><input id="live-search" type="search" placeholder="Buscar nombre o mensaje" aria-label="Buscar en conversaciones cargadas"></label><div class="tabs"><button data-live-filter="all" class="active" aria-pressed="true">Todas</button><button data-live-filter="unread" aria-pressed="false">No leídas</button><button data-live-filter="waiting" aria-pressed="false" title="Conversaciones donde lo último lo escribió el cliente">Sin respuesta</button><button data-live-filter="mine" aria-pressed="false" id="live-filter-mine" hidden>Mías</button><span class="live-channel-filters" id="live-channel-filters"></span><span id="live-count"></span></div><div id="live-list"></div></section><section id="live-messages" aria-label="Conversación"><p class="empty">Selecciona una conversación para consultar su historial.</p></section><aside id="live-contact-panel" class="live-contact-panel" aria-label="Ficha del contacto"></aside></div>`;
      const $ = (s) => container.querySelector(s), limit = $("#live-limit"), feedback = $("#live-feedback"), layout = $(".live-inbox-layout");
      const mineButton = $("#live-filter-mine");
      if (mineButton && me?.user?.identity_type === "external") mineButton.hidden = false;
      const select = { get value() {
        return workspace.current();
      }, set disabled(v) {
      } };
      if (!items.length) {
        feedback.textContent = workspace.noAccountText("las conversaciones");
        return;
      }
      const PULSE = 60, CHAT_FALLBACK = 120;
      let disposed = false, timer3 = null, chatTimer = null, pollTimer = null, interval = 120, pause = 0, syncBusy = false, messageBusy = 0, chatNeedsRefresh = false;
      let focusWanted = false;
      try {
        focusWanted = localStorage.getItem("agora-focus") === "1";
      } catch {
      }
      document.body.classList.remove("agora-focus");
      const canRefresh = () => active() && !disposed && !document.hidden && navigator.onLine;
      const autoStatus = $("#live-auto-status");
      async function pulseTick() {
        if (!canRefresh()) return;
        if (syncBusy || messageBusy || [...drafts.values()].some((d) => d.sending)) {
          schedulePulse(3);
          return;
        }
        const connection = select.value;
        try {
          const beat = await request2(`workspace/pulse?connectionId=${encodeURIComponent(connection)}`);
          if (!active() || connection !== select.value) return;
          if (beat.retryAfter) pause = Math.max(pause, Date.now() / 1e3 + Number(beat.retryAfter));
          window.dispatchEvent(new CustomEvent("agora:unread", { detail: Number(beat.unread) || 0 }));
          say(beat.reason === "paused" ? "En espera unos segundos para respetar la cuota de la cuenta" : beat.reason === "failed" ? "Reintentando la conexión con la cuenta" : "");
          if (beat.token && beat.token !== pulseToken) {
            const first = pulseToken === null;
            pulseToken = beat.token;
            if (!first) {
              await load(true);
              if (active() && selected && !messageBusy) await page(false, true);
            }
          }
        } catch (e) {
          if (e.status === 401) {
            container.agoraLiveDispose();
            return;
          }
        } finally {
          schedulePulse();
        }
      }
      async function pollChat() {
        if (!canRefresh() || !selected) return;
        if (syncBusy || messageBusy || drafts.get(select.value + ":" + selected.id)?.sending) {
          scheduleChat(3);
          return;
        }
        await page(false, true);
      }
      container.agoraLiveDispose = () => {
        disposed = true;
        document.body.classList.remove("agora-focus");
        clearTimeout(timer3);
        clearTimeout(chatTimer);
        clearTimeout(pollTimer);
        clearTimeout(pulseTimer);
        document.removeEventListener("visibilitychange", wake);
        window.removeEventListener("online", wake);
        window.removeEventListener("offline", wake);
      };
      document.addEventListener("visibilitychange", wake);
      window.addEventListener("online", wake);
      window.addEventListener("offline", wake);
      async function tick() {
        if (!canRefresh()) return;
        if (syncBusy || messageBusy || [...drafts.values()].some((d) => d.sending)) {
          schedule2(5);
          return;
        }
        if (pause > Date.now() / 1e3) {
          schedule2(pause - Date.now() / 1e3);
          return;
        }
        await sync(true);
      }
      let generation = 0, chatGeneration = 0, conversations = [], selected = null, filter = "all", nextCursor = null, hasMore = false, loadingMore = false, pulseToken = null, pulseTimer = null, messages = /* @__PURE__ */ new Map(), cursor = null, chatChannel = null, chatChannels = null, channelFilter = "", searchTerm = "", searchTimer = null, channelNames = {}, templates = { open: false, items: null, configured: true, error: "", chosen: "", values: {}, sending: false, notice: "", generation: -1 };
      if (["waiting", "unread", "mine"].includes(workspace.pendingFilter)) {
        filter = workspace.pendingFilter;
        workspace.pendingFilter = null;
        container.querySelectorAll("[data-live-filter]").forEach((n) => {
          const on = n.dataset.liveFilter === filter;
          n.classList.toggle("active", on);
          n.setAttribute("aria-pressed", String(on));
        });
      }
      const inboxQuery = () => {
        const parts = [`connectionId=${encodeURIComponent(select.value)}`, `limit=${encodeURIComponent(limit.value || "20")}`];
        if (filter === "unread") parts.push("status=unread");
        if (filter === "mine") parts.push("mine=1");
        if (filter === "waiting") parts.push("direction=inbound");
        if (channelFilter) parts.push("channel=" + encodeURIComponent(channelFilter));
        if (searchTerm) parts.push("query=" + encodeURIComponent(searchTerm));
        return parts.join("&");
      };
      const loadTags = () => request2(`workspace/tags?connectionId=${encodeURIComponent(select.value)}`).then((r) => asList6(r.items));
      let listLoading = false;
      async function page_more() {
        if (loadingMore || !nextCursor) return;
        loadingMore = true;
        list();
        const connection = select.value, version = generation, cursor2 = nextCursor;
        try {
          const data = await request2(`workspace/inbox?${inboxQuery()}&startAfter=${encodeURIComponent(cursor2)}`);
          if (version !== generation || !active() || connection !== select.value) return;
          const known = new Set(conversations.map((c) => c.id));
          const added = asList6(data.items).filter((c) => !known.has(c.id));
          conversations = conversations.concat(added);
          nextCursor = data.nextCursor || null;
          hasMore = data.hasMore === true && !!nextCursor && added.length > 0;
          feedback.textContent = `${conversations.length} de ${data.total} conversaciones`;
        } catch (e) {
          if (e.status === 401) container.agoraLiveDispose();
          if (active()) feedback.textContent = friendly(e);
          hasMore = false;
        } finally {
          loadingMore = false;
          if (active()) list();
        }
      }
      async function page(older = false, refresh = false) {
        messageBusy++;
        const current2 = chatGeneration, connection = select.value, conversation = selected.id, previous = older ? cursor : null;
        const more = $("#live-older"), retry = $("#live-retry");
        more.disabled = true;
        if (!refresh) {
          retry.hidden = true;
          $("#live-chat-status").textContent = older ? "Cargando mensajes anteriores…" : "";
          if (!older && !messages.size) $("#live-log").innerHTML = skeleton("chat");
        }
        try {
          const r = await request2(`workspace/messages?connectionId=${encodeURIComponent(connection)}&conversationId=${encodeURIComponent(conversation)}${previous ? "&lastMessageId=" + encodeURIComponent(previous) : ""}`);
          if (!active() || current2 !== chatGeneration) return;
          if (Array.isArray(r.channels) && r.channels.length) {
            chatChannels = r.channels;
            const d = drafts.get(connection + ":" + conversation);
            if (d && $("#live-composer")) applyChannels(selected, chatChannels, d);
          }
          const log = $("#live-log"), height = log.scrollHeight, top = log.scrollTop, hadMessages = messages.size > 0;
          for (const m of r.items) messages.set(m.id, m);
          if (!refresh || !hadMessages) cursor = r.hasMore && r.lastMessageId && r.lastMessageId !== previous ? r.lastMessageId : null;
          chatNeedsRefresh = false;
          renderMessages();
          more.hidden = !cursor;
          $("#live-chat-status").textContent = r.hasMore && !cursor ? "No se recibió una referencia válida para continuar el historial." : "";
          if (refresh) say();
          if (older) log.scrollTop = top + log.scrollHeight - height;
          else if (refresh && height - top - log.clientHeight > 60) log.scrollTop = top;
          else scrollEnd(log, refresh && hadMessages);
        } catch (e) {
          if (e.retryAfter) pause = Math.max(pause, Date.now() / 1e3 + e.retryAfter);
          if (e.status === 401) container.agoraLiveDispose();
          if (active() && current2 === chatGeneration) {
            if (!refresh || !messages.size) {
              $("#live-log .sk-wrap")?.remove();
              $("#live-chat-status").textContent = friendly(e);
              retry.hidden = false;
              retry.onclick = () => page(older);
            } else say("Reintentando la actualización del chat");
          }
        } finally {
          messageBusy--;
          if (active() && current2 === chatGeneration) {
            more.disabled = false;
            if (refresh) scheduleChat(effectiveChatDelay());
          }
        }
      }
      const channelMarks = {
        WhatsApp: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2Z" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="M8.6 7.9c.3-.1.7 0 .9.4l.7 1.4c.1.3.1.6-.1.8l-.5.6c-.1.2-.2.4 0 .7.5.9 1.3 1.7 2.2 2.2.3.2.5.1.7 0l.6-.5c.2-.2.5-.2.8-.1l1.4.7c.4.2.5.6.4.9-.3.8-1.1 1.3-2 1.2-3-.3-5.6-2.9-5.9-5.9-.1-.9.4-1.7 1.2-2Z" fill="currentColor"/></svg>',
        IG: '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="5.2" fill="none" stroke="currentColor" stroke-width="1.7"/><circle cx="12" cy="12" r="4.1" fill="none" stroke="currentColor" stroke-width="1.7"/><circle cx="17.2" cy="6.8" r="1.25" fill="currentColor"/></svg>',
        FB: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2.6c-5.3 0-9.4 3.9-9.4 9 0 2.9 1.3 5.4 3.5 7.1v3.6l3.2-1.8c.9.2 1.8.4 2.7.4 5.3 0 9.4-3.9 9.4-9s-4.1-9.3-9.4-9.3Z" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="m6.9 14.3 3.5-3.7 1.9 1.9 3-1.9-3.5 3.7-1.9-1.9-3 1.9Z" fill="currentColor"/></svg>',
        SMS: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4.8h16a1.2 1.2 0 0 1 1.2 1.2v9.6a1.2 1.2 0 0 1-1.2 1.2H9.2L4.6 20.4V16.8H4A1.2 1.2 0 0 1 2.8 15.6V6A1.2 1.2 0 0 1 4 4.8Z" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><circle cx="8.4" cy="10.8" r="1.1" fill="currentColor"/><circle cx="12" cy="10.8" r="1.1" fill="currentColor"/><circle cx="15.6" cy="10.8" r="1.1" fill="currentColor"/></svg>',
        Email: '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="2.8" y="5" width="18.4" height="14" rx="2" fill="none" stroke="currentColor" stroke-width="1.7"/><path d="m3.4 6.6 8.6 6 8.6-6" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/></svg>'
      };
      const CHANNEL_REASONS = { needs_phone: "Este contacto no tiene teléfono en su ficha.", needs_email: "Este contacto no tiene correo en su ficha.", needs_thread: "Sólo se puede responder por aquí si el contacto escribió primero." };
      const commentTypes = { TYPE_INSTAGRAM_COMMENT: "Instagram", TYPE_FACEBOOK_COMMENT: "Facebook" };
      const threadChannel = (c) => sendChannels[c.lastMessageType] ? [{ type: sendChannels[c.lastMessageType], label: commentTypes[c.lastMessageType] ? "Mensaje directo en " + commentTypes[c.lastMessageType] : channel(c.lastMessageType), needsSubject: sendChannels[c.lastMessageType] === "Email" }] : [];
      const usableChannels = (offered) => asList6(offered).filter((o) => o.available !== false);
      const CHEVRON = '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>';
      const pickerMarkup = (offered) => {
        const list2 = asList6(offered);
        if (list2.length < 2 && !list2.some((o) => o.available === false)) return `<p class="live-channel-single">Respondes por ${esc7(list2[0]?.label ?? "")}</p>`;
        const chosen = list2.find((o) => o.type === chatChannel && o.available !== false) || list2.find((o) => o.available !== false) || list2[0];
        const items2 = list2.map((o) => {
          const off = o.available === false;
          const title = off ? ` title="${esc7(CHANNEL_REASONS[o.reason] || "No disponible para este contacto.")}"` : "";
          return `<button type="button" role="menuitemradio" aria-checked="${o.type === chatChannel && !off}" data-channel="${esc7(o.type)}" class="channel-${esc7(o.type)}${o.type === chatChannel && !off ? " selected" : ""}${off ? " unavailable" : ""}"${off ? ' disabled aria-disabled="true"' : ""} aria-pressed="${o.type === chatChannel && !off}"${title}><span class="live-channel-mark" aria-hidden="true">${channelMarks[o.type] || ""}</span><span class="live-channel-label">${esc7(o.label)}</span>${off ? `<small>${esc7(CHANNEL_REASONS[o.reason] || "No disponible")}</small>` : ""}</button>`;
        }).join("");
        return `<div class="channel-menu"><button type="button" class="channel-current" data-ch="${esc7(chosen?.type ?? "")}" aria-haspopup="menu" aria-expanded="false" aria-label="Canal de envío: ${esc7(chosen?.label ?? "")}"><span class="live-channel-mark" aria-hidden="true">${channelMarks[chosen?.type] || ""}</span><span class="live-channel-label">${esc7(chosen?.label ?? "")}</span><span class="channel-chevron">${CHEVRON}</span></button><div class="channel-list" role="menu" hidden>${items2}</div></div>`;
      };
      const recordatorios = /* @__PURE__ */ new Map();
      const recordatorioDe = (id) => recordatorios.get(id) || null;
      const canWin = () => {
        const caps = me?.user?.capabilities || {};
        return caps.viewOpportunities !== false && caps.editOpportunities !== false;
      };
      const money3 = (value) => money(value) ?? String(value);
      async function winDialog(c) {
        const dialog2 = document.querySelector("#action-dialog"), content = document.querySelector("#dialog-content");
        const connection = select.value, version = chatGeneration;
        content.innerHTML = '<h2>¿Venta ganada?</h2><p class="dialog-copy">Cargando las oportunidades de este contacto…</p>';
        if (!dialog2.open) dialog2.showModal();
        let found, pipes;
        try {
          [found, pipes] = await Promise.all([
            request2(`workspace/opportunities?connectionId=${encodeURIComponent(connection)}&conversationId=${encodeURIComponent(c.id)}`),
            request2(`workspace/pipelines?connectionId=${encodeURIComponent(connection)}`)
          ]);
        } catch (error) {
          content.innerHTML = `<h2>¿Venta ganada?</h2><p class="dialog-copy">${esc7(friendly(error))}</p>`;
          return;
        }
        if (!dialog2.open || version !== chatGeneration) return;
        const open2 = asList6(found.items).filter((o) => o.status !== "won" && o.status !== "lost" && o.status !== "abandoned");
        const pipelines = asList6(pipes.pipelines);
        const chosen = found.active && open2.some((o) => o.id === found.active.id) ? found.active.id : open2[0]?.id || "new";
        const valueOf = (id) => {
          const o = open2.find((x) => x.id === id);
          return o && typeof o.monetaryValue === "number" && o.monetaryValue > 0 ? o.monetaryValue : "";
        };
        content.innerHTML = `<h2>¿Venta ganada?</h2>
    <p class="dialog-copy">Se marca como <b>ganada</b> y cuenta en las ventas de este mes, en el tablero y en las de quien la tiene asignada. Si no tiene a nadie, pasa a ti.</p>
    <form id="win-form" class="dialog-form">
     <label>Venta<select name="opportunity">${open2.map((o) => `<option value="${esc7(o.id)}"${o.id === chosen ? " selected" : ""}>${esc7(o.name || "Oportunidad sin nombre")}${o.stageName ? " · " + esc7(o.stageName) : ""}</option>`).join("")}<option value="new"${chosen === "new" ? " selected" : ""}>+ Nueva venta</option></select></label>
     <div id="win-new" ${chosen === "new" ? "" : "hidden"}>
      <label>Nombre<input name="name" maxlength="190" value="${esc7("Venta · " + name(c))}"></label>
      ${pipelines.length > 1 ? `<label>Embudo<select name="pipeline">${pipelines.map((p) => `<option value="${esc7(p.id)}">${esc7(p.name)}</option>`).join("")}</select></label>` : ""}
     </div>
     <label>Valor de la venta<input name="value" type="number" inputmode="decimal" min="0" step="0.01" required value="${esc7(String(valueOf(chosen)))}" placeholder="Ej.: 1200"></label>
     <div class="dialog-actions"><button class="primary">Marcar como ganada</button></div>
     <p id="win-result" class="dialog-copy" role="status"></p>
    </form>`;
        const form = content.querySelector("#win-form"), result = content.querySelector("#win-result");
        form.elements.opportunity.onchange = () => {
          const v = form.elements.opportunity.value;
          content.querySelector("#win-new").hidden = v !== "new";
          form.elements.value.value = String(valueOf(v));
        };
        form.elements.value.focus();
        form.onsubmit = async (e) => {
          e.preventDefault();
          const target = form.elements.opportunity.value, value = Number(form.elements.value.value);
          if (!Number.isFinite(value) || value < 0) {
            result.textContent = "Escribe el valor de la venta.";
            return;
          }
          if (target === "new" && !pipelines.length) {
            result.textContent = "Esta cuenta no tiene embudos donde guardar la venta.";
            return;
          }
          const payload = target === "new" ? { name: form.elements.name.value.trim() || "Venta · " + name(c), pipelineId: form.elements.pipeline?.value || pipelines[0].id, status: "won", monetaryValue: value } : { status: "won", monetaryValue: value, claimIfUnassigned: true };
          const where = target === "new" ? `workspace/opportunity/new?connectionId=${encodeURIComponent(connection)}&contactId=${encodeURIComponent(c.contactId)}` : `workspace/opportunity/edit?connectionId=${encodeURIComponent(connection)}&opportunityId=${encodeURIComponent(target)}`;
          const digest = await crypto.subtle.digest("SHA-256", new TextEncoder().encode(where + JSON.stringify(payload)));
          const storageKey = "agora-win:" + Array.from(new Uint8Array(digest), (b) => b.toString(16).padStart(2, "0")).join("");
          const requestId = sessionStorage.getItem(storageKey) || crypto.randomUUID().replace(/-/g, "");
          sessionStorage.setItem(storageKey, requestId);
          const button = form.querySelector("button.primary");
          button.disabled = true;
          result.textContent = "Guardando…";
          try {
            await request2(where, "POST", me.csrf, { ...payload, requestId });
            sessionStorage.removeItem(storageKey);
            window.dispatchEvent(new Event("agora:opportunity-edited"));
            dialog2.close();
            if (recordatorios.has(c.id)) {
              recordatorios.delete(c.id);
              pintarRecordatorio(c);
              list();
            }
            window.dispatchEvent(new CustomEvent("agora:toast", { detail: `Venta ganada: ${money3(value)}. Ya cuenta en el tablero del mes.` }));
            if (active() && version === chatGeneration) loadContact(c, chatGeneration);
          } catch (error) {
            if (error.code === "nothing_to_change") {
              sessionStorage.removeItem(storageKey);
              result.textContent = "Esa venta ya estaba ganada con ese valor.";
            } else {
              if (["action_rejected", "invalid_opportunity_value", "invalid_opportunity_name", "unknown_pipeline", "action_forbidden"].includes(error.code)) sessionStorage.removeItem(storageKey);
              result.textContent = friendly(error);
            }
            button.disabled = false;
          }
        };
      }
      const FIELD_NAMES = { "contact.name": "nombre del contacto", "contact.first_name": "nombre", "contact.last_name": "apellidos", "contact.full_name": "nombre completo", "contact.phone": "teléfono", "contact.email": "correo", "contact.company_name": "empresa", "user.name": "tu nombre", "user.first_name": "tu nombre", "location.name": "nombre de la empresa" };
      const CATEGORY = { MARKETING: "Promoción", UTILITY: "Servicio", AUTHENTICATION: "Verificación" };
      async function loadTemplates(c, refresh = false) {
        const version = chatGeneration;
        templates.items = null;
        templates.error = "";
        syncTemplates(c);
        try {
          const data = await request2(`workspace/templates?connectionId=${encodeURIComponent(select.value)}${refresh ? "&refresh=1" : ""}`);
          if (version !== chatGeneration || !active()) return;
          templates.items = Array.isArray(data.items) ? data.items : [];
          templates.configured = data.configured !== false;
        } catch (error) {
          if (version !== chatGeneration || !active()) return;
          templates.items = [];
          templates.error = friendly(error);
        }
        syncTemplates(c);
      }
      async function sendTemplate(c) {
        const t = chosenTemplate();
        if (!t || templates.sending) return;
        const connection = select.value, version = chatGeneration, key = connection + ":" + c.id;
        const body = { channel: "WhatsApp", template: { id: t.id, values: templates.values } };
        templates.sending = true;
        templates.notice = "";
        syncTemplates(c);
        let storageKey;
        try {
          const digest = await crypto.subtle.digest("SHA-256", new TextEncoder().encode(JSON.stringify(body)));
          storageKey = "agora-send:" + key + ":" + Array.from(new Uint8Array(digest), (b) => b.toString(16).padStart(2, "0")).join("");
          const requestId = sessionStorage.getItem(storageKey) || crypto.randomUUID();
          sessionStorage.setItem(storageKey, requestId);
          const result = await request2(`workspace/send?connectionId=${encodeURIComponent(connection)}&conversationId=${encodeURIComponent(c.id)}`, "POST", me.csrf, { ...body, requestId });
          sessionStorage.removeItem(storageKey);
          if (version !== chatGeneration || !active()) return;
          const shown2 = $(".tpl-preview")?.innerText || t.body;
          messages.set(result.messageId, { id: result.messageId, body: shown2, direction: "outbound", dateAdded: (/* @__PURE__ */ new Date()).toISOString(), status: result.deliveryStatus });
          renderMessages();
          scrollEnd($("#live-log"), true);
          templates.open = false;
          templates.chosen = "";
          templates.values = {};
          const drafted = drafts.get(key);
          if (drafted) {
            drafted.notice = "Plantilla aceptada por el canal.";
          }
          const notice = $("#live-send-status");
          if (notice) notice.textContent = "Plantilla aceptada por el canal.";
        } catch (error) {
          templates.notice = friendly(error);
          if (storageKey && ["send_rejected", "template_values", "template_unknown", "template_unsupported", "needs_phone", "number_missing"].includes(error.code)) sessionStorage.removeItem(storageKey);
        } finally {
          templates.sending = false;
          if (version === chatGeneration && active()) syncTemplates(c);
        }
      }
      const amount2 = (value) => typeof value === "number" && isFinite(value) ? money(value) : null;
      const dayOnly = (value) => {
        if (!value) return "";
        const d = new Date(value);
        return Number.isNaN(d.getTime()) ? "" : new Intl.DateTimeFormat("es", { dateStyle: "medium" }).format(d);
      };
      const asList6 = (value) => Array.isArray(value) ? value : [];
      async function loadContact(c, version) {
        const panel2 = $("#live-contact-panel");
        panel2.innerHTML = skeleton("lines", 6);
        try {
          let bindEdit = function() {
            const form = panel2.querySelector("#live-contact-form");
            if (!form) return;
            const status = panel2.querySelector("#live-contact-status");
            tags = bindTagEditor(panel2, data.contact, loadTags);
            bindEditorExtras(panel2, data.contact, tags, () => {
              editing = false;
              show2();
            });
            form.onsubmit = async (event) => {
              event.preventDefault();
              const save2 = panel2.querySelector("#live-save-contact");
              save2.disabled = true;
              status.textContent = "Guardando ficha…";
              const fields = readContactForm(form, data.contact);
              const payload = { fields, addTags: tags.added, removeTags: tags.dropped, requestId: crypto.randomUUID().replace(/-/g, "") };
              try {
                await request2(`workspace/activity/contact?connectionId=${encodeURIComponent(select.value)}&conversationId=${encodeURIComponent(c.id)}`, "POST", me.csrf, payload);
                const fresh = await request2(`workspace/contact?connectionId=${encodeURIComponent(select.value)}&conversationId=${encodeURIComponent(c.id)}&refresh=1`);
                if (!active() || version !== chatGeneration) return;
                data.contact = fresh.contact;
                data.contact.canEdit = fresh.canEdit === true;
                editing = false;
                show2();
              } catch (error) {
                const failed = panel2.querySelector("#live-contact-status");
                if (failed) failed.textContent = friendly(error);
                const button = panel2.querySelector("#live-save-contact");
                if (button) button.disabled = false;
              }
            };
          };
          const data = await request2(`workspace/contact?connectionId=${encodeURIComponent(select.value)}&conversationId=${encodeURIComponent(c.id)}`);
          if (!active() || version !== chatGeneration) return;
          const assign = $("#live-assign");
          if (assign && data.canAssign === true && asList6(data.assignees).length) {
            assign.hidden = false;
            assign.onclick = () => assignDialog(c, data.assignees, data.contact?.assignedTo || c.assignedTo || "");
          }
          if (Array.isArray(data.channels) && data.channels.length) {
            chatChannels = data.channels;
            const draft = drafts.get(select.value + ":" + c.id);
            const readonly = $(".live-readonly");
            if (!$("#live-composer") && readonly) {
              readonly.outerHTML = composer(c, chatChannels);
              bindComposer(c);
            } else if (draft) applyChannels(c, chatChannels, draft);
          }
          let bindActivity = () => {
          }, bindOpportunities = () => {
          };
          const bind = () => {
            $("#live-profile-close").onclick = () => layout.classList.remove("contact-open");
            const edit2 = $("#live-edit-contact");
            if (edit2) edit2.onclick = () => {
              editing = true;
              show2();
            };
            bindActivity();
            bindOpportunities();
          };
          let labels = Object.fromEntries(asList6(data.fields).map((field) => [field.id, field.name])), opportunities = null, activity = null, editing = false;
          const editor = { open: null, pipelines: null, notice: "", directory: () => asList6(data.fields) };
          data.contact.canEdit = data.canEdit === true;
          const show2 = () => {
            if (editing) {
              panel2.innerHTML = contactForm(data.contact, data.fields, data.assignees);
              bindEdit();
              return;
            }
            panel2.innerHTML = contactProfile(data.contact, labels, opportunities, activity, editor);
            bind();
          };
          let tags = { added: [], dropped: [] };
          show2();
          const query = `connectionId=${encodeURIComponent(select.value)}&conversationId=${encodeURIComponent(c.id)}`;
          const reload = async () => {
            try {
              const result = await request2(`workspace/activity?${query}&refresh=1`);
              if (!active() || version !== chatGeneration) return;
              activity = result;
            } catch (error) {
              if (!active() || version !== chatGeneration) return;
              activity = { error: friendly(error) };
            }
            show2();
          };
          const act = async (path, payload, pending, done) => {
            const status = $("#live-activity-status");
            if (status) status.textContent = pending;
            const digest = await crypto.subtle.digest("SHA-256", new TextEncoder().encode(path + JSON.stringify(payload)));
            const storageKey = "agora-action:" + c.id + ":" + Array.from(new Uint8Array(digest), (b) => b.toString(16).padStart(2, "0")).join("");
            const requestId = sessionStorage.getItem(storageKey) || crypto.randomUUID().replace(/-/g, "");
            sessionStorage.setItem(storageKey, requestId);
            try {
              await request2(`workspace/activity/${path}?${query}`, "POST", me.csrf, { ...payload, requestId });
              sessionStorage.removeItem(storageKey);
              await reload();
              const settled = $("#live-activity-status");
              if (settled) settled.textContent = done;
            } catch (error) {
              if (["action_rejected", "invalid_note", "invalid_task", "invalid_due_date", "invalid_task_id", "invalid_task_state", "action_forbidden"].includes(error.code)) sessionStorage.removeItem(storageKey);
              const failed = $("#live-activity-status");
              if (failed) failed.textContent = friendly(error);
            }
          };
          bindActivity = () => {
            panel2.querySelectorAll("[data-task]").forEach((button) => button.onclick = async () => {
              panel2.querySelectorAll("[data-task]").forEach((other) => other.disabled = true);
              await act("task-state", { taskId: button.dataset.task, completed: button.dataset.completed !== "1" }, "Actualizando tarea…", "Tarea actualizada.");
            });
            const taskForm = $("#live-task-form");
            if (taskForm) taskForm.onsubmit = async (event) => {
              event.preventDefault();
              const title = $("#live-task-title").value.trim(), due = $("#live-task-due").value, body = $("#live-task-body").value.trim();
              if (!title || !due) return;
              taskForm.querySelector("button").disabled = true;
              await act("task", { title, dueDate: due, ...body ? { body } : {} }, "Creando tarea…", "Tarea creada.");
            };
            const noteForm = $("#live-note-form");
            if (noteForm) noteForm.onsubmit = async (event) => {
              event.preventDefault();
              const body = $("#live-note-body").value.trim();
              if (!body) return;
              noteForm.querySelector("button").disabled = true;
              await act("note", { body }, "Guardando nota…", "Nota guardada.");
            };
          };
          const openEditor = async (which) => {
            editor.open = which;
            editor.notice = "";
            show2();
            if (!editor.pipelines) {
              try {
                editor.pipelines = await request2(`workspace/pipelines?connectionId=${encodeURIComponent(select.value)}`);
              } catch (error) {
                editor.open = null;
                editor.notice = friendly(error);
              }
              if (!active() || version !== chatGeneration) return;
              show2();
            }
          };
          const saveOpportunity = async (form) => {
            const status = form.querySelector("[data-status]"), save2 = form.querySelector("[data-save]");
            const target = editor.open === "new" ? null : asList6(opportunities?.items).find((item) => item.id === editor.open) || null;
            const payload = readOpportunityForm(form, target);
            if (!payload.name && !target) {
              status.textContent = "Escribe un nombre para la oportunidad.";
              return;
            }
            if (Object.keys(payload).length === 0) {
              status.textContent = "No has cambiado nada en la oportunidad.";
              return;
            }
            save2.disabled = true;
            status.textContent = "Guardando la oportunidad…";
            const digest = await crypto.subtle.digest("SHA-256", new TextEncoder().encode(editor.open + JSON.stringify(payload)));
            const storageKey = "agora-opportunity:" + c.id + ":" + Array.from(new Uint8Array(digest), (b) => b.toString(16).padStart(2, "0")).join("");
            const requestId = sessionStorage.getItem(storageKey) || crypto.randomUUID().replace(/-/g, "");
            sessionStorage.setItem(storageKey, requestId);
            const where = editor.open === "new" ? `workspace/opportunity/new?connectionId=${encodeURIComponent(select.value)}&contactId=${encodeURIComponent(data.contact.id)}` : `workspace/opportunity/edit?connectionId=${encodeURIComponent(select.value)}&opportunityId=${encodeURIComponent(editor.open)}`;
            try {
              await request2(where, "POST", me.csrf, { ...payload, requestId });
              sessionStorage.removeItem(storageKey);
              window.dispatchEvent(new Event("agora:opportunity-edited"));
              try {
                opportunities = await request2(`workspace/opportunities?connectionId=${encodeURIComponent(select.value)}&conversationId=${encodeURIComponent(c.id)}&refresh=1`);
              } catch (error) {
                opportunities = { error: friendly(error) };
              }
              if (!active() || version !== chatGeneration) return;
              editor.open = null;
              editor.notice = "Oportunidad guardada.";
              show2();
            } catch (error) {
              if (["action_rejected", "invalid_opportunity_name", "invalid_opportunity_value", "unknown_pipeline", "unknown_stage", "unknown_assignee", "assignee_forbidden", "invalid_status", "nothing_to_change", "action_forbidden"].includes(error.code)) sessionStorage.removeItem(storageKey);
              const failed = panel2.querySelector("[data-status]");
              if (failed) failed.textContent = friendly(error);
              const button = panel2.querySelector("[data-save]");
              if (button) button.disabled = false;
            }
          };
          bindOpportunities = () => {
            const add = panel2.querySelector("[data-new-opportunity]");
            if (add) add.onclick = () => openEditor("new");
            panel2.querySelectorAll("[data-edit-opportunity]").forEach((button) => button.onclick = () => openEditor(button.dataset.editOpportunity));
            const form = panel2.querySelector(".opportunity-form");
            if (form) {
              if (editor.pipelines) bindPipelineChoice(form, editor.pipelines.pipelines);
              form.querySelector("[data-cancel]").onclick = () => {
                editor.open = null;
                editor.notice = "";
                show2();
              };
              form.onsubmit = (event) => {
                event.preventDefault();
                saveOpportunity(form);
              };
            }
          };
          bindActivity();
          (async () => {
            if (!asList6(data.fields).length) {
              try {
                const fields = await request2(`workspace/contact-fields?connectionId=${encodeURIComponent(select.value)}`);
                if (!active() || version !== chatGeneration) return;
                data.fields = fields.items;
                labels = Object.fromEntries(fields.items.map((field) => [field.id, field.name]));
                show2();
              } catch {
              }
            }
            if (!active() || version !== chatGeneration) return;
            try {
              opportunities = await request2(`workspace/opportunities?connectionId=${encodeURIComponent(select.value)}&conversationId=${encodeURIComponent(c.id)}`);
            } catch (error) {
              opportunities = { error: friendly(error) };
            }
            if (!active() || version !== chatGeneration) return;
            show2();
            try {
              activity = await request2(`workspace/activity?${query}`);
            } catch (error) {
              activity = { error: friendly(error) };
            }
            if (!active() || version !== chatGeneration) return;
            show2();
          })();
        } catch (error) {
          if (active() && version === chatGeneration) panel2.innerHTML = `<p class="empty">${esc7(friendly(error))}</p>`;
        }
      }
      async function load(preserve = false) {
        if (!preserve) {
          listLoading = true;
          const l = $("#live-list");
          if (l) delete l.dataset.painted;
        }
        try {
          return await loadInner(preserve);
        } finally {
          if (listLoading) {
            listLoading = false;
            list();
          }
        }
      }
      async function loadInner(preserve = false) {
        const version = ++generation, connection = select.value;
        if (!preserve) {
          clearTimeout(pollTimer);
          chatNeedsRefresh = false;
          chatGeneration++;
          selected = null;
          conversations = [];
          list();
          layout.classList.remove("chat-open");
          $("#live-messages").innerHTML = '<p class="empty">Selecciona una conversación para consultar su historial.</p>';
          feedback.textContent = "";
        }
        try {
          const data = await request2(`workspace/inbox?${inboxQuery()}`);
          if (version !== generation || !active() || connection !== select.value) return false;
          const updated = selected ? data.items.find((c) => c.id === selected.id) : null;
          const changed = !!selected && (!messages.size || !!updated && [updated.lastMessageDate, updated.lastMessageBody, updated.unreadCount].join("|") !== [selected.lastMessageDate, selected.lastMessageBody, selected.unreadCount].join("|"));
          if (data.restricted === true && mineButton) {
            mineButton.hidden = true;
            if (filter === "mine") {
              filter = "all";
              container.querySelectorAll("[data-live-filter]").forEach((n) => {
                const on = n.dataset.liveFilter === "all";
                n.classList.toggle("active", on);
                n.setAttribute("aria-pressed", String(on));
              });
            }
          }
          if (updated) for (const field of ["lastMessageDate", "lastMessageBody", "unreadCount"]) selected[field] = updated[field];
          interval = Math.max(60, Number(data.syncIntervalSeconds) || 120);
          pause = Math.max(pause, Number(data.state?.request_not_before) || 0);
          conversations = data.items;
          limit.value = String(data.limit || 20);
          if (data.reminders && typeof data.reminders === "object") {
            recordatorios.clear();
            for (const [id, r] of Object.entries(data.reminders)) recordatorios.set(id, r);
          }
          nextCursor = data.nextCursor || null;
          hasMore = data.hasMore === true && !!nextCursor;
          if (data.channels && Object.keys(data.channels).length) {
            channelNames = data.channels;
            paintChannelFilters();
          }
          list();
          const wanted = workspace.pendingConversation;
          let unavailable = null;
          if (wanted && !selected) {
            workspace.pendingConversation = null;
            let target = conversations.find((c) => c.id === wanted.id) || (wanted.contactId ? wanted : null);
            if (!target) {
              try {
                target = (await request2(`workspace/conversation?connectionId=${encodeURIComponent(connection)}&conversationId=${encodeURIComponent(wanted.id)}`)).conversation;
              } catch (error) {
                if (error.status === 404 && !wanted.tried) {
                  for (const other of workspace.connections.map((c) => String(c.id)).filter((id) => id !== String(connection))) {
                    try {
                      await request2(`workspace/conversation?connectionId=${encodeURIComponent(other)}&conversationId=${encodeURIComponent(wanted.id)}`);
                      workspace.pendingConversation = { id: wanted.id, explicit: true, tried: true };
                      workspace.select(other);
                      return false;
                    } catch {
                    }
                  }
                }
                unavailable = error.status === 404 ? "Esa conversación ya no está disponible en esta cuenta." : friendly(error);
              }
            }
            try {
              sessionStorage.removeItem("agora-open");
            } catch {
            }
            if (target && version === generation && active()) {
              if (!conversations.some((c) => c.id === target.id)) {
                conversations = [target, ...conversations];
                list();
              }
              open(conversations.find((c) => c.id === target.id) || target);
            }
          }
          window.dispatchEvent(new CustomEvent("agora:unread", { detail: Number(data.unread) || 0 }));
          const scope = [filter === "unread" ? "no leídas" : filter === "mine" ? "mías" : filter === "waiting" ? "sin respuesta" : null, channelNames[channelFilter], searchTerm ? `«${searchTerm}»` : null].filter(Boolean).join(" · ");
          feedback.textContent = data.items.length ? `${data.items.length} de ${data.total} conversaciones${scope ? " · " + scope : ""}${data.partial ? " · ventana parcial" : ""}` : scope ? `Ninguna conversación con este filtro (${scope}).` : "Esta cuenta todavía no tiene conversaciones.";
          if (data.stale) feedback.textContent += data.reason === "paused" ? ` · Mostrando la última lista, de ${date(data.syncedAt)}; la consulta está en espera.` : ` · Mostrando la última lista, de ${date(data.syncedAt)}; no se pudo leer la cuenta ahora.`;
          if (unavailable) feedback.textContent = unavailable;
          return changed;
        } catch (e) {
          if (e.status === 401) container.agoraLiveDispose();
          if (version === generation && active()) feedback.textContent = friendly(e);
          return false;
        }
      }
      $("#live-search").oninput = (event) => {
        clearTimeout(searchTimer);
        const value = event.target.value.trim();
        searchTimer = setTimeout(() => {
          if (value === searchTerm) return;
          searchTerm = value;
          load();
        }, 450);
      };
      container.querySelectorAll("[data-live-filter]").forEach((b) => b.onclick = () => {
        if (filter === b.dataset.liveFilter) return;
        filter = b.dataset.liveFilter;
        container.querySelectorAll("[data-live-filter]").forEach((n) => {
          n.classList.toggle("active", n === b);
          n.setAttribute("aria-pressed", String(n === b));
        });
        load();
      });
      async function sync(automatic = false) {
        if (window.AGORA_NATIVE_BACKEND && !window.__DEMO__) { await load(true); if (!automatic) feedback.textContent="Datos locales actualizados. La sincronización externa no está habilitada."; schedule2(120); return; }
        if (syncBusy) return;
        syncBusy = true;
        clearTimeout(timer3);
        clearTimeout(chatTimer);
        const connection = select.value, requested = limit.value;
        limit.disabled = true;
        if (!automatic) feedback.textContent = "Actualizando conversaciones…";
        let changed = false;
        try {
          await request2(`workspace/sync?connectionId=${encodeURIComponent(connection)}&limit=${requested}`, "POST", me.csrf);
          if (active() && connection === select.value) changed = await load(true);
          say();
        } catch (e) {
          if (e.status === 401) {
            container.agoraLiveDispose();
            say("Inicia sesión para reanudar");
          } else {
            pause = Math.max(pause, Date.now() / 1e3 + (e.retryAfter || interval));
            if (active() && connection === select.value) changed = await load(true);
            if (active()) say(e.retryAfter ? "En espera unos segundos para respetar la cuota de la cuenta" : friendly(e));
          }
          if (!automatic && active()) feedback.textContent = friendly(e);
        } finally {
          syncBusy = false;
          limit.disabled = false;
          chatNeedsRefresh = chatNeedsRefresh || changed;
          if (canRefresh() && chatNeedsRefresh && selected && !messageBusy && pause <= Date.now() / 1e3 + 1) {
            const version = chatGeneration;
            chatTimer = setTimeout(() => {
              if (canRefresh() && version === chatGeneration && !messageBusy) page(false, true);
            }, 1200);
          }
          schedule2(Math.max(interval, pause - Date.now() / 1e3));
        }
      }
      limit.onchange = () => sync();
      await load();
      if (!conversations.length) sync(true);
      schedule2(interval);
      schedulePulse(2);
    } catch (e) {
      if (!active()) return;
      container.innerHTML = `<h2>Conversaciones</h2><p class="settings-notice">${esc7(friendly(e))} Inicia sesión con tu cuenta de equipo.</p><button id="live-login" class="primary">Iniciar sesión</button>`;
      container.querySelector("#live-login").onclick = () => document.querySelector("#connect").click();
    }
  }

  // agora/contacts.js
  var esc8 = (s) => String(s ?? "").replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" })[c]);
  var initials2 = (name2) => String(name2 || "·").split(/\s+/).slice(0, 2).map((part) => [...part][0] || "").join("").toUpperCase() || "··";
  var asList4 = (value) => Array.isArray(value) ? value : [];
  async function request3(path, method = "GET", body = null) {
    const csrf2 = method !== "GET" ? workspace.me?.csrf || "" : "";
    const r = await fetch((window.AGORA_API || "/api/") + path, { method, cache: "no-store", ...body !== null ? { body: JSON.stringify(body) } : {}, headers: { "Content-Type": "application/json", ...csrf2 ? { "X-CSRF-Token": csrf2 } : {} } });
    let data;
    try {
      data = await r.json();
    } catch {
      throw Object.assign(Error(`El servidor devolvió una respuesta no válida (HTTP ${r.status}).`), { status: r.status });
    }
    if (!r.ok) throw Object.assign(Error((data.error || "Error de conexión") + (data.retryAfter ? ` Espera ${data.retryAfter} segundos.` : "")), { status: r.status, code: data.code });
    return data;
  }
  var DETAILS = [["email", "Correo"], ["phone", "Teléfono"], ["companyName", "Empresa"], ["website", "Sitio web"], ["address", "Dirección"], ["country", "País"], ["timezone", "Zona horaria"], ["source", "Fuente"], ["dateOfBirth", "Fecha de nacimiento"], ["assignedName", "Responsable"]];
  async function renderContacts(container) {
    const instance = /* @__PURE__ */ Symbol();
    container.agoraViewInstance = instance;
    const active = () => container.agoraViewInstance === instance && container.dataset.view === "contacts";
    const connection = workspace.current();
    if (!connection) {
      container.innerHTML = `<p class="empty">${esc8(workspace.noAccountText("contactos"))}</p>`;
      return;
    }
    container.innerHTML = `<div class="records-layout" id="records-layout">
   <div class="records-list">
    <div class="live-toolbar"><label class="search"><span aria-hidden="true">⌕</span><input id="contact-search" type="search" placeholder="Buscar por nombre, correo o teléfono" aria-label="Buscar contactos"></label></div>
    <p id="contact-feedback" class="live-feedback" role="status"></p>
    <div id="contact-results" role="list">${skeleton("list", 7)}</div>
   </div>
   <section class="records-record" id="contact-record" aria-live="polite"><p class="empty">Elige una persona de la lista para ver y editar su ficha.</p></section>
  </div>`;
    const $ = (s) => container.querySelector(s);
    const layout = $("#records-layout");
    let timer3 = null, generation = 0, selected = null;
    function rows2(items) {
      return items.map((c) => `<button class="record-row${c.id === selected ? " selected" : ""}" data-contact="${esc8(c.id)}" role="listitem">
      <span class="avatar olive">${esc8(initials2(c.name))}</span>
      <span class="record-row-text"><strong>${esc8(c.name)}</strong><small>${esc8(c.email || c.phone || c.companyName || "Sin datos de contacto")}</small>${asList4(c.tags).length ? `<span class="record-row-tags">${c.tags.slice(0, 3).map((t) => `<span class="tag">${esc8(t)}</span>`).join("")}</span>` : ""}</span>
    </button>`).join("");
    }
    const loadTags = () => request3(`workspace/tags?connectionId=${encodeURIComponent(connection)}`).then((r) => asList4(r.items));
    async function load(search = "", after = "") {
      const version = ++generation;
      $("#contact-feedback").textContent = "";
      if (!$("#contact-results").children.length || $("#contact-results .sk-wrap")) $("#contact-results").innerHTML = skeleton("list", 7);
      try {
        const data = await request3(`workspace/contacts?connectionId=${encodeURIComponent(connection)}${search ? "&query=" + encodeURIComponent(search) : ""}${after ? "&cursor="+encodeURIComponent(after):""}`);
        if (!active() || version !== generation) return;
        $("#contact-feedback").textContent = data.total ? `${data.total} contacto${data.total === 1 ? "" : "s"}${data.restricted ? " asignados a tu usuario" : ""}${data.partial ? " · página de hasta 100 registros" : ""}` : search ? "Ningún contacto coincide con esa búsqueda." : "Esta cuenta todavía no tiene contactos.";
        $("#contact-results").innerHTML = rows2(asList4(data.items))+(data.hasMore?'<button class="btn" id="contacts-next" type="button">Página siguiente</button>':'');
        $("#contacts-next")?.addEventListener("click",()=>load(search,data.nextCursor));
        bindRows();
      } catch (error) {
        if (!active() || version !== generation) return;
        $("#contact-feedback").textContent = friendly(error);
        $("#contact-results").innerHTML = "";
        if (error.status === 401) window.dispatchEvent(new Event("agora:logout"));
      }
    }
    function bindRows() {
      container.querySelectorAll("[data-contact]").forEach((button) => button.onclick = () => open(button.dataset.contact));
    }
    let record = null, editing = false, tags = { added: [], dropped: [] };
    const canWrite = () => {
      const c = workspace.me?.user?.capabilities || {};
      return c.viewConversations !== false && c.sendMessages !== false;
    };
    async function open(contactId) {
      selected = contactId;
      editing = false;
      record = null;
      layout.classList.add("record-open");
      container.querySelectorAll("[data-contact]").forEach((b) => b.classList.toggle("selected", b.dataset.contact === contactId));
      $("#contact-record").innerHTML = '<p class="empty">Abriendo la ficha…</p>';
      try {
        const data = await request3(`workspace/record?connectionId=${encodeURIComponent(connection)}&contactId=${encodeURIComponent(contactId)}`);
        if (!active() || selected !== contactId) return;
        record = data;
        show2();
      } catch (error) {
        if (!active() || selected !== contactId) return;
        $("#contact-record").innerHTML = `<p class="empty">${esc8(friendly(error))}</p>`;
        if (error.status === 401) window.dispatchEvent(new Event("agora:logout"));
      }
    }
    function show2() {
      const panel2 = $("#contact-record");
      const contact = record.contact;
      if (editing) {
        panel2.innerHTML = contactForm(contact, asList4(record.fields), asList4(record.assignees));
        tags = bindTagEditor(panel2, contact, loadTags);
        bindEditorExtras(panel2, contact, tags, () => {
          editing = false;
          show2();
        });
        panel2.querySelector("#live-contact-form").onsubmit = save2;
        return;
      }
      const details = DETAILS.map(([key, label]) => contact[key] ? `<div><span>${esc8(label)}</span><strong>${esc8(contact[key])}</strong></div>` : "").join("") || "<div><span>Sin datos</span><strong>Esta ficha está vacía.</strong></div>";
      const filled2 = asList4(contact.customFields).filter((field) => field.value !== "" && field.value !== null && field.value !== void 0);
      const custom = filled2.length ? groupByFolder(filled2, asList4(record.fields)).map((g) => `<div class="folder-title">${esc8(g.title)}</div>` + g.items.map((field) => `<div><span>${esc8(field.name || asList4(record.fields).find((d) => d.id === field.id)?.name || "Campo personalizado")}</span><strong>${esc8(Array.isArray(field.values) && field.values.length ? field.values.join(", ") : field.value)}</strong></div>`).join("")).join("") : "<div><span>Campos personalizados</span><strong>Sin valores todavía.</strong></div>";
      panel2.innerHTML = `<header class="record-header">
      <button class="mobile-back" id="record-back" aria-label="Volver a la lista">←</button>
      <span class="avatar olive">${esc8(initials2(contact.name))}</span>
      <div><h2>${esc8(contact.name)}</h2><p>${esc8(contact.email || contact.phone || "Sin datos de contacto")}</p></div>
      <div class="record-actions">${canWrite() ? '<button class="primary" id="record-message">Enviar mensaje</button>' : ""}${record.canEdit ? '<button class="secondary" id="record-edit">Editar ficha</button>' : ""}</div>
     </header>
     ${contact.dnd ? '<p class="record-flag">Marcado como no contactar.</p>' : ""}
     <div class="record-columns">
      <div><div class="section-label">Datos</div><div class="live-profile-details">${details}</div></div>
      <div><div class="section-label">Campos personalizados</div><div class="live-profile-fields">${custom}</div>
       <div class="section-label">Etiquetas</div><div class="live-profile-tags">${asList4(contact.tags).length ? contact.tags.map((t) => `<span class="tag">${esc8(t)}</span>`).join("") : '<span class="muted">Sin etiquetas</span>'}</div></div>
     </div>
     <p id="record-status" class="live-feedback" role="status"></p>`;
      panel2.querySelector("#record-back").onclick = () => {
        layout.classList.remove("record-open");
      };
      const edit2 = panel2.querySelector("#record-edit");
      if (edit2) edit2.onclick = () => {
        editing = true;
        show2();
      };
      const write = panel2.querySelector("#record-message");
      if (write) write.onclick = async () => {
        write.disabled = true;
        const status = panel2.querySelector("#record-status");
        status.textContent = "Abriendo la conversación…";
        try {
          const { conversation } = await request3(`workspace/contact-conversation?connectionId=${encodeURIComponent(connection)}`, "POST", { contactId: selected });
          if (!active()) return;
          window.dispatchEvent(new CustomEvent("agora:open-conversation", { detail: conversation }));
        } catch (error) {
          status.textContent = friendly(error);
          write.disabled = false;
        }
      };
    }
    async function save2(event) {
      event.preventDefault();
      const form = event.target, status = form.querySelector("#live-contact-status"), button = form.querySelector("#live-save-contact");
      const fields = readContactForm(form, record.contact);
      if (Object.keys(fields).length === 0 && !tags.added.length && !tags.dropped.length) {
        status.textContent = "No has cambiado nada todavía.";
        return;
      }
      button.disabled = true;
      status.textContent = "Guardando la ficha…";
      try {
        await request3(
          `workspace/record?connectionId=${encodeURIComponent(connection)}&contactId=${encodeURIComponent(selected)}`,
          "POST",
          { fields, addTags: tags.added, removeTags: tags.dropped, requestId: crypto.randomUUID().replace(/-/g, "") }
        );
        const fresh = await request3(`workspace/record?connectionId=${encodeURIComponent(connection)}&contactId=${encodeURIComponent(selected)}&refresh=1`);
        if (!active()) return;
        record = fresh;
        editing = false;
        show2();
        const row = container.querySelector(`[data-contact="${CSS.escape(selected)}"] strong`);
        if (row) row.textContent = fresh.contact.name;
      } catch (error) {
        if (!active()) return;
        status.textContent = friendly(error);
        if (error.status === 401) window.dispatchEvent(new Event("agora:logout"));
      } finally {
        if (active()) button.disabled = false;
      }
    }
    $("#contact-search").oninput = (e) => {
      clearTimeout(timer3);
      const value = e.target.value.trim();
      timer3 = setTimeout(() => load(value), 450);
    };
    container.agoraLiveDispose = () => {
      clearTimeout(timer3);
      container.agoraViewInstance = null;
    };
    await load();
  }

  // agora/won.js
  var esc9 = (s) => String(s ?? "").replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" })[c]);
  var MONTHS2 = ["enero", "febrero", "marzo", "abril", "mayo", "junio", "julio", "agosto", "septiembre", "octubre", "noviembre", "diciembre"];
  var pad = (n) => String(n).padStart(2, "0");
  var monthOptions2 = (selected, count = 24) => {
    const now = /* @__PURE__ */ new Date(), seen = [];
    for (let i = 0; i < count; i++) {
      const d = new Date(now.getFullYear(), now.getMonth() - i, 1);
      seen.push(`${d.getFullYear()}-${pad(d.getMonth() + 1)}`);
    }
    if (selected && !seen.includes(selected)) seen.push(selected);
    return seen.map((v) => {
      const [y, m] = v.split("-");
      const t = MONTHS2[Number(m) - 1];
      return `<option value="${v}"${v === selected ? " selected" : ""}>${t[0].toUpperCase() + t.slice(1)} ${y}</option>`;
    }).join("");
  };
  var dateLabel = (v) => {
    const d = v ? new Date(v) : null;
    return d && !Number.isNaN(d.getTime()) ? new Intl.DateTimeFormat("es-ES", { day: "numeric", month: "short" }).format(d) : "";
  };
  async function request4(path, method = "GET", body = null) {
    const r = await fetch((window.AGORA_API || "/api/") + path, { method, cache: "no-store", headers: { "Content-Type": "application/json", ...workspace.me?.csrf && method !== "GET" ? { "X-CSRF-Token": workspace.me.csrf } : {} }, ...body ? { body: JSON.stringify(body) } : {} });
    let data;
    try {
      data = await r.json();
    } catch {
      throw Object.assign(Error(`El servidor devolvió una respuesta no válida (HTTP ${r.status}).`), { status: r.status });
    }
    if (!r.ok) throw Object.assign(Error((data.error || "Error de conexión") + (data.retryAfter ? ` Espera ${data.retryAfter} segundos.` : "")), { status: r.status, code: data.code });
    return data;
  }
  async function renderWon(pane, active = () => true) {
    const connection = workspace.current();
    let period = (/* @__PURE__ */ new Date()).toISOString().slice(0, 7), seller = "", data = null, generation = 0;
    pane.innerHTML = `<div class="won-toolbar"><label>Mes<select id="won-month">${monthOptions2(period)}</select></label><label id="won-seller-box" hidden>Vendedor<select id="won-seller"><option value="">Todos</option></select></label></div><div id="won-summary"></div><div id="won-list">${skeleton("list", 5)}</div>`;
    const $ = (s) => pane.querySelector(s);
    async function load() {
      const mine = ++generation;
      try {
        const d = await request4(`workspace/won?connectionId=${encodeURIComponent(connection)}&period=${period}${seller ? "&seller=" + encodeURIComponent(seller) : ""}`);
        if (!active() || mine !== generation) return;
        data = d;
        paint();
      } catch (error) {
        if (active() && mine === generation) {
          $("#won-summary").innerHTML = "";
          $("#won-list").innerHTML = `<p class="empty">${esc9(friendly(error))}</p>`;
        }
      }
    }
    function paint() {
      const t = data.totals;
      $("#won-summary").innerHTML = `<div class="won-stats"><div><span>Ventas ganadas</span><strong>${number(t.count)}</strong></div><div><span>Monto vendido</span><strong>${esc9(money(t.sum) || "—")}</strong></div><div class="${t.missing ? "bad" : ""}"><span>Sin monto</span><strong>${number(t.missing)}</strong></div></div>${data.partial ? '<p class="won-note">Hay muchas ventas en estos meses, así que puede faltar alguna de las más antiguas.</p>' : ""}`;
      const box = $("#won-seller-box");
      if (data.canSeeAll && data.bySeller.length) {
        box.hidden = false;
        $("#won-seller").innerHTML = '<option value="">Todos</option>' + data.bySeller.map((b) => `<option value="${esc9(b.id ?? "none")}"${seller === (b.id ?? "none") ? " selected" : ""}>${esc9(b.name)} · ${b.count}${b.missing ? ` (${b.missing} sin monto)` : ""}</option>`).join("");
      } else box.hidden = true;
      const rows2 = data.items.map((item) => {
        const contact = item.contactName && item.contactName !== item.name ? item.contactName : null;
        const meta = [contact, item.assignedName || (item.assignedTo ? null : "Sin responsable"), dateLabel(item.wonAt)].filter(Boolean).join(" · ");
        return `<article class="won-row${item.missing ? " missing" : ""}" data-won-id="${esc9(item.id)}">
        <div class="won-row-info"><strong>${esc9(item.name || "Venta ganada")}</strong><small>${item.missing ? '<b class="won-miss">Sin monto</b>' + (meta ? " · " : "") : ""}${esc9(meta)}</small></div>
        ${item.missing ? data.canEdit ? `<form class="won-inline" data-won-form="${esc9(item.id)}"><div class="won-field"><label class="sr-only" for="wv-${esc9(item.id)}">Monto</label><span class="won-currency" aria-hidden="true">${symbol()}</span><input id="wv-${esc9(item.id)}" type="number" inputmode="decimal" min="0.01" step="0.01" placeholder="0,00" autocomplete="off"><button class="primary won-save">Guardar</button></div></form>` : "" : `<strong class="won-amount">${esc9(money(item.value) || "—")}</strong>`}
        <p class="won-inline-status" role="status"></p></article>`;
      }).join("");
      $("#won-list").innerHTML = rows2 || '<p class="empty">No hay ventas ganadas en este mes.</p>';
      pane.querySelectorAll("[data-won-form]").forEach((form) => form.onsubmit = (e) => save2(e, form));
    }
    async function save2(event, form) {
      event.preventDefault();
      const id = form.dataset.wonForm, input = form.querySelector("input"), button = form.querySelector("button"), status = form.closest(".won-row").querySelector(".won-inline-status");
      const value = Number(input.value);
      if (!(value > 0)) {
        status.textContent = "Escribe un monto mayor que cero.";
        return;
      }
      button.disabled = true;
      status.textContent = "Guardando…";
      try {
        await request4(`workspace/opportunity/edit?connectionId=${encodeURIComponent(connection)}&opportunityId=${encodeURIComponent(id)}`, "POST", { monetaryValue: value, requestId: crypto.randomUUID().replace(/-/g, "") });
        window.dispatchEvent(new Event("agora:opportunity-edited"));
        await load();
      } catch (error) {
        button.disabled = false;
        status.textContent = friendly(error);
      }
    }
    $("#won-month").onchange = (e) => {
      period = e.target.value;
      $("#won-list").innerHTML = skeleton("list", 5);
      load();
    };
    $("#won-seller").onchange = (e) => {
      seller = e.target.value;
      load();
    };
    await load();
  }

  // agora/board.js
  var esc10 = (s) => String(s ?? "").replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" })[c]);
  var amount = (value) => typeof value === "number" && isFinite(value) ? money(value) : null;
  var asList5 = (value) => Array.isArray(value) ? value : [];
  async function request5(path, method = "GET", csrf2 = "", body = null) {
    const r = await fetch((window.AGORA_API || "/api/") + path, { method, cache: "no-store", ...body !== null ? { body: JSON.stringify(body) } : {}, headers: { "Content-Type": "application/json", ...csrf2 ? { "X-CSRF-Token": csrf2 } : {} } });
    let data;
    try {
      data = await r.json();
    } catch {
      throw Object.assign(Error(`El servidor devolvió una respuesta no válida (HTTP ${r.status}).`), { status: r.status });
    }
    if (!r.ok) throw Object.assign(Error((data.error || "Error de conexión") + (data.retryAfter ? ` Espera ${data.retryAfter} segundos.` : "")), { status: r.status, code: data.code });
    return data;
  }
  async function renderFunnels(container) {
    const instance = /* @__PURE__ */ Symbol();
    container.agoraViewInstance = instance;
    const active = () => container.agoraViewInstance === instance && container.dataset.view === "board";
    const connection = workspace.current();
    if (!connection) {
      container.innerHTML = `<p class="empty">${esc10(workspace.noAccountText("oportunidades"))}</p>`;
      return;
    }
    container.innerHTML = skeleton("cards", 6);
    let generation = 0, pipeline = "", csrf2 = "", board = null, directory = null, editing = null, notice = "";
    try {
      csrf2 = (await request5("me")).csrf || "";
    } catch {
    }
    function card(item, canEdit) {
      return `<div class="card board-card"><h3>${esc10(item.name)}</h3>${item.contactName ? `<p>${esc10(item.contactName)}</p>` : ""}${amount(item.monetaryValue) ? `<strong>${esc10(amount(item.monetaryValue))}</strong>` : ""}${item.assignedName ? `<span class="tag">${esc10(item.assignedName)}</span>` : ""}${canEdit ? `<button class="live-opportunity-edit" data-edit-opportunity="${esc10(item.id)}">Editar</button>` : ""}</div>`;
    }
    function show2() {
      const data = board;
      const note = data.total ? `${data.total} oportunidad${data.total === 1 ? "" : "es"} abierta${data.total === 1 ? "" : "s"}${data.restricted ? " asignadas a tu usuario" : ""}${data.partial ? " · se muestran las más recientes" : ""}` : data.restricted ? "No tienes oportunidades abiertas asignadas en este embudo." : "Este embudo no tiene oportunidades abiertas.";
      const canEdit = data.canEdit === true;
      const cards2 = asList5(data.stages).flatMap((stage) => asList5(stage.items));
      const panel2 = editing ? `<section class="board-editor">${directory ? opportunityForm({ pipelines: directory.pipelines, assignees: directory.assignees, opportunity: cards2.find((item) => item.id === editing) || null }) : '<span class="muted">Cargando embudos…</span>'}</section>` : "";
      container.innerHTML = `<div class="live-toolbar"><label>Embudo<select id="board-pipeline" aria-label="Embudo">${data.pipelines.map((p) => `<option value="${esc10(p.id)}"${p.id === data.pipelineId ? " selected" : ""}>${esc10(p.name)}</option>`).join("")}</select></label></div><p class="live-feedback" role="status">${esc10(note)}${notice ? " · " + esc10(notice) : ""}</p>${panel2}<div class="kanban">${data.stages.map((stage) => `<div class="board-column"><h2>${esc10(stage.name)}</h2><p class="board-total">${stage.total} · ${esc10(amount(stage.value) ?? "0")}</p>${stage.items.map((item) => card(item, canEdit)).join("") || '<p class="board-empty">Sin oportunidades</p>'}</div>`).join("")}</div>`;
      container.querySelector("#board-pipeline").onchange = (e) => load(e.target.value);
      container.querySelectorAll("[data-edit-opportunity]").forEach((button) => button.onclick = () => edit2(button.dataset.editOpportunity));
      const form = container.querySelector(".opportunity-form");
      if (form) {
        if (directory) bindPipelineChoice(form, directory.pipelines);
        form.querySelector("[data-cancel]").onclick = () => {
          editing = null;
          notice = "";
          show2();
        };
        form.onsubmit = (event) => {
          event.preventDefault();
          save2(form);
        };
        form.scrollIntoView({ block: "nearest" });
      }
    }
    async function edit2(id) {
      editing = id;
      notice = "";
      show2();
      if (!directory) {
        try {
          directory = await request5(`workspace/pipelines?connectionId=${encodeURIComponent(connection)}`);
        } catch (error) {
          editing = null;
          notice = friendly(error);
        }
        if (!active()) return;
        show2();
      }
    }
    async function save2(form) {
      const cards2 = asList5(board.stages).flatMap((stage) => asList5(stage.items));
      const target = cards2.find((item) => item.id === editing) || null;
      const payload = readOpportunityForm(form, target);
      const status = form.querySelector("[data-status]"), button = form.querySelector("[data-save]");
      if (Object.keys(payload).length === 0) {
        status.textContent = "No has cambiado nada en la oportunidad.";
        return;
      }
      button.disabled = true;
      status.textContent = "Guardando la oportunidad…";
      const digest = await crypto.subtle.digest("SHA-256", new TextEncoder().encode(editing + JSON.stringify(payload)));
      const storageKey = "agora-opportunity:" + editing + ":" + Array.from(new Uint8Array(digest), (b) => b.toString(16).padStart(2, "0")).join("");
      const requestId = sessionStorage.getItem(storageKey) || crypto.randomUUID().replace(/-/g, "");
      sessionStorage.setItem(storageKey, requestId);
      try {
        await request5(`workspace/opportunity/edit?connectionId=${encodeURIComponent(connection)}&opportunityId=${encodeURIComponent(editing)}`, "POST", csrf2, { ...payload, requestId });
        sessionStorage.removeItem(storageKey);
        window.dispatchEvent(new Event("agora:opportunity-edited"));
        if (!active()) return;
        editing = null;
        notice = "Oportunidad guardada.";
        await load(pipeline);
      } catch (error) {
        if (["action_rejected", "invalid_opportunity_name", "invalid_opportunity_value", "unknown_pipeline", "unknown_stage", "unknown_assignee", "assignee_forbidden", "invalid_status", "nothing_to_change", "action_forbidden"].includes(error.code)) sessionStorage.removeItem(storageKey);
        if (!active()) return;
        const failed = container.querySelector("[data-status]");
        if (failed) failed.textContent = friendly(error);
        const retry = container.querySelector("[data-save]");
        if (retry) retry.disabled = false;
        if (error.status === 401) window.dispatchEvent(new Event("agora:logout"));
      }
    }
    async function load(selected = "") {
      const version = ++generation;
      try {
        const data = await request5(`workspace/board?connectionId=${encodeURIComponent(connection)}${selected ? "&pipelineId=" + encodeURIComponent(selected) : ""}`);
        if (!active() || version !== generation) return;
        if (data.unavailable === "pipelines") {
          container.innerHTML = '<p class="empty">No se pudieron consultar los embudos de esta cuenta.<br>Revisa el permiso de oportunidades y vuelve a entrar.</p>';
          return;
        }
        pipeline = data.pipelineId || "";
        board = data;
        show2();
      } catch (error) {
        if (!active() || version !== generation) return;
        container.innerHTML = `<p class="empty">${esc10(friendly(error))}</p>`;
        if (error.status === 401) window.dispatchEvent(new Event("agora:logout"));
      }
    }
    container.agoraLiveDispose = () => {
      container.agoraViewInstance = null;
    };
    await load(pipeline);
  }
  async function renderBoard(container) {
    const instance = /* @__PURE__ */ Symbol();
    container.agoraViewInstance = instance;
    const connection = workspace.current();
    if (!connection) {
      container.innerHTML = `<p class="empty">${esc10(workspace.noAccountText("oportunidades"))}</p>`;
      return;
    }
    let tab = workspace.pendingOppTab || sessionStorage.getItem("agora-opp-tab") || "funnels";
    workspace.pendingOppTab = null;
    if (!["funnels", "won"].includes(tab)) tab = "funnels";
    const badge = () => {
      const n = wonAlertState()?.count || 0;
      return n > 0 ? `<span class="tab-badge" aria-label="${n} sin monto">${n}</span>` : "";
    };
    container.innerHTML = `<div class="opp-tabs" role="tablist" aria-label="Oportunidades"><button type="button" role="tab" data-opp-tab="funnels">Embudos</button><button type="button" role="tab" data-opp-tab="won">Ganadas<span id="won-badge">${badge()}</span></button></div><div id="opp-pane"></div>`;
    const pane = container.querySelector("#opp-pane");
    const active = () => container.agoraViewInstance === instance && container.dataset.view === "board" && container.querySelector("#opp-pane") === pane;
    const open = (name2) => {
      tab = name2;
      try {
        sessionStorage.setItem("agora-opp-tab", name2);
      } catch {
      }
      container.querySelectorAll("[data-opp-tab]").forEach((b) => {
        const on = b.dataset.oppTab === name2;
        b.classList.toggle("on", on);
        b.setAttribute("aria-selected", String(on));
      });
      pane.dataset.view = "board";
      if (name2 === "won") renderWon(pane, active);
      else renderFunnels(pane);
    };
    container.querySelectorAll("[data-opp-tab]").forEach((b) => b.onclick = () => {
      if (b.dataset.oppTab !== tab) open(b.dataset.oppTab);
    });
    const onAlert = () => {
      const el = container.querySelector("#won-badge");
      if (!el) {
        window.removeEventListener("agora:won-alert", onAlert);
        return;
      }
      el.innerHTML = badge();
    };
    window.addEventListener("agora:won-alert", onAlert);
    open(tab);
  }

  // agora/announce.js
  var esc11 = (s) => String(s ?? "").replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" })[c]);
  function renderBody(text) {
    const bullet = /^\s*[-•]\s+/;
    return String(text || "").split(/\n{2,}/).map((block) => {
      const out = [];
      let list = [], lines2 = [];
      const flushList = () => {
        if (list.length) {
          out.push(`<ul>${list.map((x) => `<li>${esc11(x)}</li>`).join("")}</ul>`);
          list = [];
        }
      };
      const flushText = () => {
        if (lines2.length) {
          out.push(`<p>${lines2.map(esc11).join("<br>")}</p>`);
          lines2 = [];
        }
      };
      for (const line of block.split("\n")) {
        if (bullet.test(line)) {
          flushText();
          list.push(line.replace(bullet, ""));
        } else {
          flushList();
          lines2.push(line);
        }
      }
      flushList();
      flushText();
      return out.join("");
    }).join("");
  }
  function windowMarkup(item, { preview = false } = {}) {
    const link = item.linkLabel && item.linkUrl ? `<a class="announce-link primary" href="${esc11(item.linkUrl)}"${item.linkUrl.startsWith("https://") ? ' target="_blank" rel="noopener noreferrer"' : ""} data-announce-link>${esc11(item.linkLabel)}</a>` : "";
    return `<span class="eyebrow">Novedades${preview ? " · vista previa" : ""}</span><h2 class="announce-title">${esc11(item.title)}</h2>
   <div class="announce-body">${renderBody(item.body)}</div>
   <div class="dialog-actions announce-actions">${link}<button id="announce-ok" class="${link ? "link-button" : "primary"}" type="button">${preview ? "Cerrar" : "Entendido"}</button></div>`;
  }

  // agora/integrations.js
  var esc12 = (s) => String(s).replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" })[c]);
  var accessPanel = (items, channel2) => `<section class="settings-notice access-panel"><h3>Canal de acceso del equipo</h3><p>Ágora entrega los códigos temporales de acceso usando el correo de una de tus cuentas conectadas. Elige cuál se encarga. Tu acceso de administrador con contraseña funciona siempre, incluso sin canal.</p><label>Cuenta encargada<select id="access-channel-select"><option value="">Sin asignar</option>${items.map((i) => `<option value="${i.id}"${Number(channel2.connectionId) === Number(i.id) ? " selected" : ""}${i.status === "connected" ? "" : " disabled"}>${esc12(i.name)}${i.status === "connected" ? "" : " · comprueba la conexión primero"}</option>`).join("")}</select></label><p class="access-state">${channel2.connectionId ? channel2.ready ? "Listo para entregar códigos de acceso." : "La cuenta elegida necesita una comprobación correcta antes de entregar códigos." : "Sin canal asignado: los integrantes del equipo no podrán recibir códigos."}</p></section>`;
  var statusNames = { untested: "Pendiente de comprobar", connected: "Conexión comprobada", error: "Revisar conexión" };
  var csrf = "";
  async function api2(path, method = "GET", data) {
    const r = await fetch((window.AGORA_API || "/api/") + path, { method, cache: "no-store", headers: { "Content-Type": "application/json", ...method === "GET" ? {} : { "X-CSRF-Token": csrf } }, ...data ? { body: JSON.stringify(data) } : {} });
    let b;
    try {
      b = await r.json();
    } catch {
      throw Error("El backend no está disponible.");
    }
    if (!r.ok) {
      const e = Error(b.error || "No se ha podido completar la operación.");
      e.status = r.status;
      e.code = b.code;
      e.reference = b.reference;
      throw e;
    }
    return b;
  }
  var lastTestResult = "";
  var freshCron = null;
  var askCronPassword = false;
  async function devicePanel(container) {
    const panel2 = container.querySelector("#device-panel");
    if (!panel2) return;
    let data;
    try {
      data = await api2("devices");
    } catch {
      panel2.innerHTML = "";
      return;
    }
    const names = { web: "Navegador o aplicación instalada", android: "Aplicación Android" };
    const when2 = (value) => {
      if (!value) return null;
      const d = new Date(String(value).includes("T") ? value : String(value).replace(" ", "T") + "Z");
      return Number.isNaN(d.getTime()) ? String(value) : d.toLocaleString("es-ES", { dateStyle: "short", timeStyle: "short" });
    };
    const ago = (value) => {
      if (!value) return null;
      const d = new Date(value);
      const s = Math.round((Date.now() - d.getTime()) / 1e3);
      return Number.isNaN(s) ? null : s < 90 ? "hace un momento" : s < 3600 ? `hace ${Math.round(s / 60)} min` : `hace ${Math.round(s / 3600)} h`;
    };
    const ready = [data.browser ? "navegador" : null, data.android ? "Android" : null].filter(Boolean).join(" y ");
    const links = [];
    const discovered = data.discovery?.at;
    const recent = (at) => at && Date.now() - new Date(at).getTime() < 5 * 6e4;
    const via = data.discovery?.via === "url" ? " (desde la dirección de revisión)" : data.discovery?.via === "cron" ? " (desde el servidor)" : "";
    links.push(recent(discovered) ? ["ok", `Revisión de mensajes nuevos: activa, ${ago(discovered)}${via}.`] : ["bad", discovered ? `Revisión de mensajes nuevos: la última fue ${ago(discovered)}. El cron de revisión ha dejado de ejecutarse en el servidor.` : "Revisión de mensajes nuevos: el cron de revisión no se está ejecutando en el servidor. Sin él no hay avisos con la app cerrada."]);
    const delivered = data.delivery;
    if (delivered?.configured === false) links.push(["bad", "Entrega de avisos: no encuentra las claves de avisos. Revisa la línea de configuración y que la carpeta privada sea legible por el cron."]);
    else if (delivered?.error) links.push(["bad", `Entrega de avisos: la última pasada falló (${delivered.error}).`]);
    else links.push(recent(delivered?.at) ? ["ok", `Entrega de avisos: activa, ${ago(delivered.at)}${delivered.sent ? ` · ${delivered.sent} enviados en la última pasada` : ""}.`] : ["bad", delivered?.at ? `Entrega de avisos: la última fue ${ago(delivered.at)}. La hace el cron de revisión: revisa que siga ejecutándose.` : "Entrega de avisos: todavía no ha corrido. La hace el cron de revisión, cada minuto."]);
    const q = data.queue || {};
    links.push([q.failed ? "bad" : "ok", `Últimos 7 días: ${Number(q.sent) || 0} avisos entregados, ${Number(q.pending) || 0} pendientes, ${Number(q.failed) || 0} fallidos.${q.lastError ? ` Último error: ${q.lastError}.` : ""}`]);
    panel2.innerHTML = `<div class="section-label">Avisos en dispositivos</div><p class="settings-notice">${ready ? `Preparado para avisar por ${esc12(ready)}. Cada persona activa los avisos desde su propio dispositivo.` : "Todavía no hay avisos preparados en el servidor. Revisa la configuración privada antes de activarlos en un dispositivo."}</p>
  <ul class="push-chain">${links.map(([kind, text]) => `<li class="${kind}">${esc12(text)}</li>`).join("")}</ul>
  ${externalCheck(data, recent(discovered))}
  <button id="push-test" class="primary">Enviar un aviso de prueba a mis dispositivos</button>
  <p id="push-test-result" class="dialog-copy" role="status">${esc12(lastTestResult)}</p>
  <div class="device-list">${(data.items || []).map((d) => `<article class="card"><h3>${esc12(d.label || names[d.platform] || "Dispositivo")}</h3><small>${esc12(names[d.platform] || d.platform)}<br>Registrado: ${esc12(when2(d.created_at) || "—")}<br>Visto: ${esc12(when2(d.last_seen_at) || "—")}<br>Último aviso enviado: ${esc12(when2(d.last_sent_at) || "Ninguno todavía")}</small></article>`).join("") || '<p class="empty">Ningún dispositivo ha activado los avisos todavía.</p>'}</div>`;
    bindExternalCheck(container, panel2);
    const RESULT = { rejected: "el servicio de avisos rechazó el envío; el dispositivo sigue en la lista y se volverá a intentar", sent: "enviado", unregistered: "el servicio ya no reconoce este dispositivo y se ha quitado de la lista; vuelve a activar los avisos en él", unavailable: "el servicio de avisos no respondió; vuelve a probar en un momento", unconfigured: "faltan las claves de avisos en el servidor", invalid_credentials: "las claves de avisos del servidor no se pueden leer", unexpected: "error inesperado en el servidor" };
    panel2.querySelector("#push-test").onclick = async (event) => {
      const button = event.currentTarget, out = panel2.querySelector("#push-test-result");
      button.disabled = true;
      out.textContent = "Enviando…";
      try {
        const { results = [], error } = await api2("devices/test", "POST");
        if (error) {
          lastTestResult = error;
          return;
        }
        lastTestResult = results.length ? results.map((r) => `${r.label || names[r.platform] || "Dispositivo"}: ${RESULT[r.result] || r.result}`).join(" · ") + (results.some((r) => r.result === "sent") ? ". Si un dispositivo dice «enviado» y no muestra nada, el aviso salió de aquí y el teléfono lo retuvo: revisa los ajustes de notificaciones del sistema para Ágora y el ahorro de batería." : "") : "Este usuario no tiene ningún dispositivo con los avisos activados. Actívalos primero en el teléfono, con el mismo usuario.";
      } catch (error) {
        lastTestResult = friendly(error);
      } finally {
        button.disabled = false;
        out.textContent = lastTestResult;
        devicePanel(container);
      }
    };
  }
  function externalCheck(data, running) {
    const created = data.externalCheck;
    const copyRow = (id, label, value) => `<label class="external-check-label">${label}</label><div class="external-check-url"><input id="${id}" readonly value="${esc12(value)}" aria-label="${label}"><button type="button" data-copy="${id}">Copiar</button></div>`;
    if (freshCron) return `<div class="external-check"><h3>Clave de revisión creada</h3>
   <p>Cópiala ahora: <strong>no se volverá a mostrar</strong>. Si la pierdes, crea otra y la anterior dejará de funcionar.</p>
   ${copyRow("cron-endpoint", "Dirección", freshCron.endpoint)}
   ${copyRow("cron-header", "Nombre de la cabecera", freshCron.header)}
   ${copyRow("cron-key", "Valor de la cabecera (la clave)", freshCron.key)}
   <ol><li>Entra en <strong>cron-job.org</strong> (es gratis) y crea una tarea nueva con la <strong>dirección</strong> de arriba, <strong>cada minuto</strong>.</li>
   <li>En la pestaña <strong>Avanzado</strong>, en <strong>Cabeceras</strong>, añade una con el nombre <code>${esc12(freshCron.header)}</code> y la clave como valor.</li>
   <li>Guarda. En un par de minutos la revisión saldrá aquí en verde.</li></ol>
   <p class="muted">Así la clave viaja dentro de la petición y no queda escrita en la dirección, ni en registros ni en historiales.</p>
   <details><summary>Mi servicio de cron no permite cabeceras</summary><p class="muted">Usa esta dirección, que lleva la clave dentro. Funciona igual, pero la clave puede quedar en registros de servidores intermedios.</p>${copyRow("cron-url", "Dirección con la clave", freshCron.url)}</details>
   <button id="cron-done" type="button">Hecho</button></div>`;
    const ask = askCronPassword ? `<form id="cron-confirm" class="dialog-form cron-confirm"><label>Para crear la clave, confirma tu contraseña<input type="password" name="password" autocomplete="current-password" required></label><button class="primary">Crear clave</button> <button type="button" id="cron-cancel" class="link-button">Cancelar</button><p id="cron-confirm-result" class="dialog-copy" role="status"></p></form>` : "";
    if (created) {
      const use = data.externalCheckUse;
      const used = use?.at ? `Último uso: ${esc12(new Date(use.at).toLocaleString("es-ES"))}${use.how === "address" ? " · con la clave en la dirección" : ""}.` : "Todavía no se ha usado.";
      return `<div class="external-check"><p>La revisión desde fuera está activada desde el ${esc12(new Date(created).toLocaleDateString("es-ES"))}. ${used}</p>
   ${ask || '<button id="cron-new" type="button">Cambiar la clave</button> <button id="cron-off" type="button" class="danger">Desactivar</button>'}</div>`;
    }
    if (running && !ask) return "";
    return `<div class="external-check"><h3>La revisión no está corriendo en el servidor</h3>
   <p>Sin ella los avisos sólo salen cuando alguien tiene Ágora abierta. Si el cron del servidor no se puede poner o no funciona, un servicio de cron externo puede hacer la revisión llamando cada minuto a Ágora con una clave.</p>
   ${ask || '<button id="cron-new" class="primary" type="button">Crear clave de revisión</button>'}</div>`;
  }
  function bindExternalCheck(container, panel2) {
    const create = panel2.querySelector("#cron-new");
    if (create) create.onclick = () => {
      if (document.querySelector("#cron-off") && !confirm("La clave actual dejará de funcionar. ¿Crear una nueva?")) return;
      askCronPassword = true;
      devicePanel(container);
    };
    const form = panel2.querySelector("#cron-confirm");
    if (form) {
      form.elements.password.focus();
      form.onsubmit = async (e) => {
        e.preventDefault();
        const button = form.querySelector("button.primary");
        button.disabled = true;
        try {
          freshCron = await api2("admin/cron-url", "POST", { password: form.elements.password.value });
          askCronPassword = false;
          devicePanel(container);
        } catch (error) {
          form.querySelector("#cron-confirm-result").textContent = friendly(error);
          button.disabled = false;
        }
      };
      panel2.querySelector("#cron-cancel").onclick = () => {
        askCronPassword = false;
        devicePanel(container);
      };
    }
    const off = panel2.querySelector("#cron-off");
    if (off) off.onclick = async () => {
      if (!confirm("La revisión desde fuera dejará de funcionar. ¿Desactivarla?")) return;
      off.disabled = true;
      try {
        await api2("admin/cron-url", "DELETE");
      } catch (error) {
        alert(friendly(error));
      }
      devicePanel(container);
    };
    panel2.querySelectorAll("[data-copy]").forEach((copy) => copy.onclick = async () => {
      const input = panel2.querySelector("#" + copy.dataset.copy);
      try {
        await navigator.clipboard.writeText(input.value);
        copy.textContent = "Copiado";
      } catch {
        input.select();
        copy.textContent = "Selecciona y copia";
      }
    });
    const done = panel2.querySelector("#cron-done");
    if (done) done.onclick = () => {
      freshCron = null;
      devicePanel(container);
    };
  }
  function backupNotice(b) {
    const day = 864e5;
    if (!b) return "";
    const when2 = new Date(b.at);
    const old = Date.now() - when2.getTime() > 2 * day;
    if (!b.ok) return `<p class="settings-notice backup-notice warn"><b>Copias de seguridad:</b> la última, el ${esc12(when2.toLocaleString("es-ES"))}, falló${b.error === "passphrase_short" ? ": la frase de cifrado necesita al menos 12 caracteres" : ". Revisa los permisos de la carpeta de copias"}.</p>`;
    return `<p class="settings-notice backup-notice${old || !b.encrypted ? " warn" : ""}"><b>Copias de seguridad:</b> la última, el ${esc12(when2.toLocaleString("es-ES"))}${old ? " (hace más de dos días: revisa la tarea diaria)" : ""}. ${b.encrypted ? "Cifrada, con la base de datos y las claves." : "Sólo la base de datos: añade una frase de cifrado para que incluya también las claves."}</p>`;
  }
  async function renderIntegrations(container) {
    const instance = /* @__PURE__ */ Symbol();
    container.agoraIntegrationsInstance = instance;
    const active = () => container.agoraIntegrationsInstance === instance && container.dataset.view === "settings";
    container.innerHTML = '<p class="empty">Comprobando tu sesión…</p>';
    let user;
    try {
      const me = await api2("me");
      user = me.user;
      csrf = me.csrf;
      if (!active()) return;
    } catch (e) {
      if (!active()) return;
      container.innerHTML = `<h2>Conexiones de Ágora</h2><p class="settings-notice">${e.status === 401 ? "Inicia sesión con tu usuario administrador para configurar las subcuentas." : "No se ha podido conectar con el backend. Comprueba su estado."}</p><button id="settings-login" class="primary">Iniciar sesión</button>`;
      container.querySelector("#settings-login").onclick = () => window.dispatchEvent(new Event("agora:logout"));
      return;
    }
    if (user.role !== "administrator") {
      container.innerHTML = '<h2>Acceso restringido</h2><p class="settings-notice">Sólo el administrador puede gestionar las integraciones. Esta configuración está reservada al administrador.</p>';
      return;
    }
    try {
      const installation = await api2("admin/diagnostics");
      if (!active()) return;
      if (!installation.ready) {
        renderSetup(container, installation);
        return;
      }
      const { items, accessChannel = { connectionId: null, ready: false }, backup = null } = await api2("admin/integrations");
      if (!active()) return;
      container.innerHTML = `<div class="integration-heading"><div><span class="eyebrow">Administración</span><h2 style="margin-top:8px">Cuentas conectadas</h2></div><div><button id="test-access-channel"${accessChannel.ready ? "" : " disabled"}>Probar canal de acceso</button> <button id="add-integration" class="primary">+ Añadir subcuenta</button></div></div><p class="settings-notice">Tu cuenta es la única administración propia de Ágora. El equipo se importa desde cada cuenta conectada con sus permisos y asignaciones; no se crean contraseñas locales para empleados.</p>${accessPanel(items, accessChannel)}<div id="integration-cards">${items.map((i) => `<article class="card integration-card"><span class="integration-status">${statusNames[i.status] || "Pendiente"}</span><h3>${esc12(i.name)}</h3><p>Identificador: ${esc12(i.location_id)}<br>Clave protegida · intervalo: ${i.sync_interval_seconds} s<br>Última comprobación: ${esc12(i.last_checked_at || "Sin comprobar")}<br>Consultas programadas: ${Number(i.sync_enabled) ? "activadas" : "desactivadas"}<br>Última lectura: ${esc12(i.last_success_at || "Sin sincronizar")}<br>Equipo importado: ${Number(i.externalUsers) || 0} · ${esc12(i.users_last_success_at || "Sin sincronizar")}${i.accessChannel ? "<br>Canal de acceso del equipo" : ""}<br>Plantillas de WhatsApp: ${i.whatsapp_number_id ? "número " + esc12(i.whatsapp_number_id) : "sin configurar"}</p><footer><button data-edit="${i.id}">Configurar conexión</button><button data-whatsapp="${i.id}">Plantillas de WhatsApp</button><button class="secondary" data-fields-check="${i.id}">Carpetas de campos</button><button data-commission="${i.id}">Comisión</button><button data-test="${i.id}">Comprobar conexión</button><button data-users="${i.id}">Equipo y permisos</button><button data-schedule="${i.id}" data-enabled="${Number(i.sync_enabled) || 0}">${Number(i.sync_enabled) ? "Pausar consultas" : "Activar consultas"}</button><button data-remove="${i.id}" class="danger">Eliminar</button></footer></article>`).join("") || '<p class="empty">Todavía no tienes cuentas conectadas.<br>Añade una cuenta para empezar.</p>'}</div><p id="integration-feedback" class="dialog-copy" role="status"></p>${backupNotice(backup)}<section id="announce-panel"></section><section id="device-panel"></section>`;
      devicePanel(container);
      announcePanel(container);
      container.querySelector("#add-integration").onclick = () => edit(null, container);
      container.querySelector("#test-access-channel").onclick = async (e) => {
        const boton = e.currentTarget; boton.disabled = true;
        const feedback = container.querySelector("#integration-feedback");
        feedback.textContent = "Enviando prueba…";
        try {
          await api2("admin/access-channel/test", "POST");
          feedback.textContent = "Prueba entregada al correo del administrador. Revisa la bandeja y la carpeta de spam.";
        } catch (error) {
          feedback.textContent = friendly(error);
        } finally {
          boton.disabled = false;
        }
      };
      const channelSelect = container.querySelector("#access-channel-select");
      if (channelSelect) channelSelect.onchange = async () => {
        channelSelect.disabled = true;
        const feedback = container.querySelector("#integration-feedback");
        try {
          await api2("admin/access-channel", "PUT", { connectionId: channelSelect.value ? Number(channelSelect.value) : null });
          await renderIntegrations(container);
          container.querySelector("#integration-feedback").textContent = channelSelect.value ? "Canal de acceso guardado." : "Canal de acceso sin asignar. El equipo no podrá recibir códigos.";
        } catch (error) {
          feedback.textContent = friendly(error);
          channelSelect.disabled = false;
        }
      };
      container.querySelectorAll("[data-edit]").forEach((b) => b.onclick = () => edit(items.find((i) => String(i.id) === b.dataset.edit), container));
      container.querySelectorAll("[data-test]").forEach((b) => b.onclick = async () => {
        b.disabled = true;
        try {
          const r = await api2(`admin/integrations/${b.dataset.test}/test`, "POST");
          await renderIntegrations(container);
          container.querySelector("#integration-feedback").textContent = r.message;
        } catch (e) {
          container.querySelector("#integration-feedback").textContent = friendly(e);
        } finally {
          b.disabled = false;
        }
      });
      container.querySelectorAll("[data-commission]").forEach((b) => b.onclick = () => commissionDialog(items.find((i) => String(i.id) === b.dataset.commission)));
      container.querySelectorAll("[data-fields-check]").forEach((b) => b.onclick = () => fieldsCheck(items.find((i) => String(i.id) === b.dataset.fieldsCheck)));
      container.querySelectorAll("[data-whatsapp]").forEach((b) => b.onclick = () => whatsappNumber(items.find((i) => String(i.id) === b.dataset.whatsapp), container));
      container.querySelectorAll("[data-users]").forEach((b) => b.onclick = () => team(b.dataset.users, items.find((i) => String(i.id) === b.dataset.users), container));
      container.querySelectorAll("[data-schedule]").forEach((b) => b.onclick = async () => {
        b.disabled = true;
        try {
          await api2(`admin/integrations/${b.dataset.schedule}/schedule`, "POST", { enabled: !Number(b.dataset.enabled) });
          await renderIntegrations(container);
          container.querySelector("#integration-feedback").textContent = "Preferencia guardada. La actualización automática requiere que el servicio de sincronización esté activo.";
        } catch (e) {
          container.querySelector("#integration-feedback").textContent = friendly(e);
        } finally {
          b.disabled = false;
        }
      });
      container.querySelectorAll("[data-remove]").forEach((b) => b.onclick = () => remove(b.dataset.remove, container));
    } catch (e) {
      if (!active()) return;
      container.innerHTML = `<h2>Integraciones</h2><p class="settings-notice">${esc12(friendly(e))}</p>`;
    }
  }
  async function announcePanel(container) {
    const panel2 = container.querySelector("#announce-panel");
    if (!panel2) return;
    let items = [];
    try {
      items = (await api2("admin/announcements")).items;
    } catch (error) {
      panel2.innerHTML = "";
      return;
    }
    const when2 = (value) => {
      try {
        return new Intl.DateTimeFormat("es", { dateStyle: "medium" }).format(new Date(value));
      } catch {
        return "";
      }
    };
    panel2.innerHTML = `<article class="card announce-card"><h3>Novedades para el equipo</h3>
  <p class="dialog-copy">Escribe aquí lo que quieres contar (una versión nueva, un cambio en cómo se trabaja…). Cada persona lo ve <b>una sola vez</b>, en una ventana, la próxima vez que entra en Ágora.</p>
  <form id="announce-form" class="dialog-form">
   <label>Título<input name="title" maxlength="120" required placeholder="Ej.: Nueva versión de Ágora"></label>
   <label>Texto<textarea name="body" rows="5" maxlength="2000" required placeholder="Incluye:&#10;- Primera novedad&#10;- Segunda novedad"></textarea></label>
   <p class="muted announce-hint">Las líneas que empiezan por «- » salen como lista. Una línea en blanco separa párrafos.</p>
   <div class="announce-grid">
    <label>Texto del botón (opcional)<input name="linkLabel" maxlength="40" placeholder="Ver el tablero"></label>
    <label>Dirección del botón<input name="linkUrl" maxlength="280" placeholder="/tablero o https://…"></label>
   </div>
   <label>Cuánto tiempo se muestra<select name="days"><option value="">Hasta que se retire</option><option value="7">7 días</option><option value="30">30 días</option></select></label>
   <div class="dialog-actions"><button class="primary">Publicar</button><button type="button" id="announce-preview" class="secondary">Vista previa</button></div>
   <p id="announce-result" class="dialog-copy" role="status"></p>
  </form>
  ${items.length ? `<h4>Publicadas</h4><ul class="security-list">${items.map((i) => `<li><span><b>${esc12(i.title)}</b><small>${esc12(when2(i.at))} · la han visto ${i.seen} persona${i.seen === 1 ? "" : "s"}${i.expiresAt ? " · caduca el " + esc12(when2(i.expiresAt)) : ""}</small></span><button class="link-button" data-announce-remove="${i.id}">Retirar</button></li>`).join("")}</ul>` : ""}
 </article>`;
    const form = panel2.querySelector("#announce-form"), result = panel2.querySelector("#announce-result");
    const read = () => Object.fromEntries(["title", "body", "linkLabel", "linkUrl", "days"].map((k) => [k, form.elements[k].value]));
    panel2.querySelector("#announce-preview").onclick = () => {
      const item = read();
      if (!item.title.trim() || !item.body.trim()) {
        result.textContent = "Escribe el título y el texto para ver cómo queda.";
        return;
      }
      const dialog2 = document.querySelector("#action-dialog");
      document.querySelector("#dialog-content").innerHTML = windowMarkup(item, { preview: true });
      dialog2.showModal();
      document.querySelector("#announce-ok").onclick = () => dialog2.close();
    };
    form.onsubmit = async (e) => {
      e.preventDefault();
      const button = form.querySelector("button.primary");
      button.disabled = true;
      result.textContent = "Publicando…";
      try {
        await api2("admin/announcements", "POST", read());
        await announcePanel(container);
        panel2.querySelector("#announce-result").textContent = "Publicada. Cada persona la verá al entrar.";
      } catch (error) {
        result.textContent = friendly(error);
        button.disabled = false;
      }
    };
    panel2.querySelectorAll("[data-announce-remove]").forEach((b) => b.onclick = async () => {
      if (!confirm("¿Retirar esta novedad? La dejarán de ver quienes aún no la han visto.")) return;
      b.disabled = true;
      try {
        await api2(`admin/announcements/${b.dataset.announceRemove}`, "DELETE");
        await announcePanel(container);
      } catch (error) {
        result.textContent = friendly(error);
        b.disabled = false;
      }
    });
  }
  var monthLabel = (value) => {
    try {
      return new Intl.DateTimeFormat("es", { month: "long", year: "numeric" }).format(/* @__PURE__ */ new Date(value + "-15"));
    } catch {
      return value;
    }
  };
  var ruleText = (r) => r.kind === "per_closing" ? `${new Intl.NumberFormat("es-ES", { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(r.amount)} por cada cierre` : `${new Intl.NumberFormat("es-ES").format(r.amount)} % de la venta`;
  async function commissionDialog(item) {
    const dialog2 = document.querySelector("#action-dialog"), content = document.querySelector("#dialog-content");
    content.innerHTML = `<h2>Comisión de ${esc12(item.name)}</h2><p class="dialog-copy">Cargando…</p>`;
    if (!dialog2.open) dialog2.showModal();
    let data;
    try {
      data = await api2(`admin/integrations/${item.id}/commission`);
    } catch (error) {
      content.innerHTML = `<h2>Comisión</h2><p class="dialog-copy">${esc12(friendly(error))}</p>`;
      return;
    }
    const paint = (data2) => {
      const c = data2.current;
      content.innerHTML = `<h2>Comisión, moneda y avisos de ${esc12(item.name)}</h2>
   <form id="currency-form" class="dialog-form currency-form"><label>Moneda de esta subcuenta<select name="currency"><option value="USD"${item.currency !== "EUR" ? " selected" : ""}>Dólares ($)</option><option value="EUR"${item.currency === "EUR" ? " selected" : ""}>Euros (€)</option></select></label><p id="currency-result" class="dialog-copy" role="status"></p></form>
   <form id="reminder-form" class="dialog-form currency-form"><label>Recordar un mensaje sin leer a los (minutos)<input name="minutes" type="number" inputmode="numeric" min="0" max="1440" step="1" value="${esc12(String(item.unread_reminder_minutes ?? 10))}"></label><p class="dialog-copy">Si un mensaje sigue sin leerse pasado ese tiempo, se avisa otra vez a quien tiene asignada la conversación (a los administradores si no tiene a nadie). 0 desactiva el recordatorio.</p><div class="dialog-actions"><button class="secondary">Guardar recordatorio</button></div><p id="reminder-result" class="dialog-copy" role="status"></p></form>
   <form id="won-form" class="dialog-form currency-form"><label>Alerta de ventas sin monto: días hasta que no se pueda cerrar<input name="days" type="number" inputmode="numeric" min="0" max="90" step="1" value="${esc12(String(item.won_alert_days ?? 7))}"></label><p class="dialog-copy">Quien tiene ventas ganadas sin monto (vacío o cero) ve una alerta al entrar. Si una lleva estos días o más, la ventana ya no se puede cerrar hasta completarlas. 0 = nunca bloquea.</p><div class="dialog-actions"><button class="secondary">Guardar</button></div><p id="won-result" class="dialog-copy" role="status"></p></form>
   <p class="dialog-copy">Así se calculan las comisiones del tablero de esta subcuenta. <b>Sólo tú puedes cambiarla</b>. Vale desde este mes (${esc12(monthLabel((/* @__PURE__ */ new Date()).toISOString().slice(0, 7)))}); los meses anteriores conservan la regla con la que se calcularon.</p>
   <form id="commission-form" class="dialog-form">
    <label>Cómo se paga<select name="kind"><option value="percent"${c.kind === "percent" ? " selected" : ""}>Porcentaje de la venta</option><option value="per_closing"${c.kind === "per_closing" ? " selected" : ""}>Monto fijo por cada cierre</option></select></label>
    <label><span id="commission-unit">${c.kind === "percent" ? "Porcentaje (%)" : "Monto por cierre"}</span><input name="amount" type="number" inputmode="decimal" min="0" step="0.01" required value="${esc12(String(c.amount))}"></label>
    <div class="dialog-actions"><button class="primary">Guardar</button></div>
    <p id="commission-result" class="dialog-copy" role="status"></p>
   </form>
   <p class="dialog-copy"><b>Ahora:</b> ${esc12(ruleText(c))}${c.since ? ` · desde ${esc12(monthLabel(c.since))}` : " · la de siempre"}.</p>
   ${data2.history.length ? `<details><summary>Cambios anteriores</summary><ul class="security-list">${data2.history.map((h) => `<li><span><b>${esc12(ruleText(h))}</b><small>Desde ${esc12(monthLabel(h.since))}</small></span></li>`).join("")}</ul></details>` : ""}`;
      const wonForm = content.querySelector("#won-form");
      wonForm.onsubmit = async (e) => {
        e.preventDefault();
        const out = content.querySelector("#won-result");
        out.textContent = "Guardando…";
        try {
          const r = await api2(`admin/integrations/${item.id}/won-alert`, "PUT", { days: Number(wonForm.elements.days.value) });
          item.won_alert_days = r.days;
          out.textContent = r.days ? `Guardado. La alerta se vuelve urgente a los ${r.days} días.` : "Guardado. La alerta nunca bloqueará.";
        } catch (error) {
          out.textContent = friendly(error);
        }
      };
      const reminder = content.querySelector("#reminder-form");
      reminder.onsubmit = async (e) => {
        e.preventDefault();
        const out = content.querySelector("#reminder-result");
        out.textContent = "Guardando…";
        try {
          const r = await api2(`admin/integrations/${item.id}/reminder`, "PUT", { minutes: Number(reminder.elements.minutes.value) });
          item.unread_reminder_minutes = r.minutes;
          out.textContent = r.minutes ? `Guardado. Se recordará a los ${r.minutes} minutos.` : "Guardado. No se recordarán mensajes sin leer.";
        } catch (error) {
          out.textContent = friendly(error);
        }
      };
      const money3 = content.querySelector("#currency-form"), moneyResult = content.querySelector("#currency-result");
      money3.elements.currency.onchange = async () => {
        moneyResult.textContent = "Guardando…";
        try {
          const r = await api2(`admin/integrations/${item.id}/currency`, "PUT", { currency: money3.elements.currency.value });
          item.currency = r.currency;
          moneyResult.textContent = "Guardado. Las cifras de esta subcuenta se muestran en " + (r.currency === "EUR" ? "euros" : "dólares") + ".";
        } catch (error) {
          moneyResult.textContent = friendly(error);
        }
      };
      const form = content.querySelector("#commission-form"), result = content.querySelector("#commission-result");
      form.elements.kind.onchange = () => {
        content.querySelector("#commission-unit").textContent = form.elements.kind.value === "percent" ? "Porcentaje (%)" : "Monto por cierre";
        form.elements.amount.max = form.elements.kind.value === "percent" ? "100" : "";
      };
      form.onsubmit = async (e) => {
        e.preventDefault();
        const button = form.querySelector("button.primary");
        button.disabled = true;
        result.textContent = "Guardando…";
        try {
          paint(await api2(`admin/integrations/${item.id}/commission`, "PUT", { kind: form.elements.kind.value, amount: form.elements.amount.value }));
          content.querySelector("#commission-result").textContent = "Guardado. Vale desde este mes.";
        } catch (error) {
          result.textContent = friendly(error);
          button.disabled = false;
        }
      };
    };
    paint(data);
  }
  var DROP_REASONS = { name: "sin nombre válido", language: "sin idioma reconocible", status_pending: "en revisión de Meta", status_in_appeal: "en apelación", status_rejected: "rechazadas", status_paused: "pausadas", status_disabled: "desactivadas", status_deleted: "eliminadas", unknown: "sin motivo claro" };
  async function fieldsCheck(item) {
    const dialog2 = document.querySelector("#action-dialog"), content = document.querySelector("#dialog-content");
    content.innerHTML = `<h2>Carpetas de campos</h2><p class="dialog-copy">Leyendo los campos de <b>${esc12(item.name)}</b>…</p>`;
    if (!dialog2.open) dialog2.showModal();
    try {
      const { items } = await api2(`admin/integrations/${item.id}/fields/folders`);
      if (!items.length) {
        content.innerHTML = `<h2>Carpetas de campos</h2><p class="dialog-copy">Los campos de esta cuenta no están en carpetas.</p><div class="dialog-actions"><button class="secondary" data-close-dialog>Cerrar</button></div>`;
        content.querySelector("[data-close-dialog]").onclick = () => dialog2.close();
        return;
      }
      content.innerHTML = `<h2>Carpetas de campos</h2>
   <p class="dialog-copy">La cuenta indica en qué carpeta está cada campo, pero no siempre da su nombre. Reconócelas por los campos de muestra y ponles el nombre que tienen en la cuenta; así la ficha las muestra en ese orden.</p>
   <form id="folders-form" class="folders-form">${items.map((f, i) => `<label><span class="folder-samples">${esc12(f.samples.join(", "))}${f.count > f.samples.length ? ` y ${f.count - f.samples.length} más` : ""}</span><input name="${esc12(f.id)}" value="${esc12(f.byAdmin || f.named ? f.name : "")}" maxlength="80" placeholder="Carpeta ${i + 1}"></label>`).join("")}
    <p id="folders-result" role="status"></p><div class="dialog-actions"><button class="primary">Guardar nombres</button><button type="button" class="secondary" data-close-dialog>Cerrar</button></div></form>`;
      content.querySelector("[data-close-dialog]").onclick = () => dialog2.close();
      content.querySelector("#folders-form").onsubmit = async (event) => {
        event.preventDefault();
        const button = event.target.querySelector("button.primary"), result = content.querySelector("#folders-result");
        button.disabled = true;
        result.textContent = "Guardando…";
        const names = Object.fromEntries([...event.target.querySelectorAll("input")].map((i) => [i.name, i.value.trim()]));
        try {
          await api2(`admin/integrations/${item.id}/fields/folders`, "PUT", { names });
          result.textContent = "Guardado. La ficha usará estos nombres.";
        } catch (error) {
          result.textContent = friendly(error);
        } finally {
          button.disabled = false;
        }
      };
    } catch (error) {
      content.innerHTML = `<h2>Carpetas de campos</h2><p class="dialog-copy">${esc12(friendly(error))}</p>`;
    }
  }
  function diagnosisMarkup(d) {
    if (!d) return "";
    const dropped = Object.entries(d.dropped || {}).map(([why, n]) => `${n} ${DROP_REASONS[why] || why.replace(/^status_/, "con estado ")}`).join(", ");
    const via = d.origin === "account" ? "la cuenta de WhatsApp Business" : "el número";
    const lines2 = d.received === 0 ? `<p><b>La cuenta no devolvió ninguna plantilla</b> al pedirlas con ${via}. Si en la cuenta sí hay plantillas aprobadas, prueba a indicar el identificador de la cuenta de WhatsApp Business (WABA) en el segundo campo y vuelve a comprobar.</p>` : `<p>La cuenta devolvió <b>${d.received}</b> plantilla${d.received === 1 ? "" : "s"}; se muestran <b>${d.shown}</b> en el chat.${dropped ? ` Fuera: ${esc12(dropped)}.` : ""}</p>${(d.names || []).length ? `<p class="muted">${(d.names || []).map(esc12).join(" · ")}</p>` : ""}`;
    const fields = d.fields || {};
    return `<div class="wa-diagnosis">${lines2}<details><summary>Detalle para soporte</summary><p class="muted">Campos de la respuesta: ${esc12((fields.top || []).join(", ") || "ninguno")}<br>Campos de cada plantilla: ${esc12((fields.item || []).join(", ") || "—")}${(fields.template || []).length ? `<br>Dentro de «template»: ${esc12(fields.template.join(", "))}` : ""}<br>Comprobado: ${esc12(d.at || "")}</p></details></div>`;
  }
  function whatsappNumber(item, container) {
    const dialog2 = document.querySelector("#action-dialog");
    document.querySelector("#dialog-content").innerHTML = `<h2>Plantillas de WhatsApp</h2><p class="dialog-copy">Para escribir a un contacto pasadas 24 horas desde su último mensaje, WhatsApp exige una plantilla aprobada. Indica los identificadores que Meta asigna a <b>${esc12(item.name)}</b>; aparecen en la configuración de WhatsApp de la cuenta.</p><form id="whatsapp-form" class="dialog-form"><label>Identificador del número («Phone number ID»)<input name="numberId" inputmode="numeric" pattern="[0-9]{6,30}" value="${esc12(item.whatsapp_number_id || "")}" placeholder="Ej.: 920050454518509"></label><label>Identificador de la cuenta de WhatsApp Business («WABA ID»), opcional<input name="accountId" inputmode="numeric" pattern="[0-9]{6,30}" value="${esc12(item.whatsapp_account_id || "")}" placeholder="Sólo si las plantillas no aparecen con el número"></label><div class="dialog-actions"><button class="primary">Guardar</button><button type="button" id="whatsapp-check" class="secondary"${item.whatsapp_number_id ? "" : " disabled"}>Comprobar plantillas</button></div><p id="whatsapp-result" class="dialog-copy" role="status"></p></form><div id="whatsapp-diagnosis"></div><p class="dialog-copy">Deja el número vacío para no usar plantillas en esta cuenta.</p>`;
    if (!dialog2.open) dialog2.showModal();
    const result = document.querySelector("#whatsapp-result");
    const check3 = async () => {
      const button = document.querySelector("#whatsapp-check");
      button.disabled = true;
      result.textContent = "Pidiendo las plantillas a la cuenta…";
      try {
        const { diagnosis } = await api2(`admin/integrations/${item.id}/whatsapp/check`, "POST");
        result.textContent = "";
        document.querySelector("#whatsapp-diagnosis").innerHTML = diagnosisMarkup(diagnosis);
      } catch (error) {
        result.textContent = friendly(error);
      } finally {
        button.disabled = false;
      }
    };
    document.querySelector("#whatsapp-check").onclick = check3;
    document.querySelector("#whatsapp-form").onsubmit = async (e) => {
      e.preventDefault();
      const button = e.target.querySelector("button.primary");
      button.disabled = true;
      try {
        const value = e.target.elements.numberId.value.trim(), account = e.target.elements.accountId.value.trim();
        await api2(`admin/integrations/${item.id}/whatsapp`, "PUT", { numberId: value || null, accountId: account || null });
        item.whatsapp_number_id = value || null;
        item.whatsapp_account_id = value ? account || null : null;
        result.textContent = value ? "Guardado." : "Plantillas desactivadas en esta cuenta.";
        document.querySelector("#whatsapp-check").disabled = !value;
        if (value) await check3();
      } catch (error) {
        result.textContent = friendly(error);
      } finally {
        button.disabled = false;
      }
    };
    dialog2.addEventListener("close", () => renderIntegrations(container), { once: true });
  }
  async function team(id, item, container) {
    const dialog2 = document.querySelector("#action-dialog");
    document.querySelector("#dialog-content").innerHTML = '<h2>Equipo y permisos</h2><p class="dialog-copy">Cargando usuarios importados…</p>';
    dialog2.showModal();
    async function show2(message = "") {
      try {
        const data = await api2(`admin/integrations/${id}/users`);
        if (!dialog2.open) return;
        document.querySelector("#dialog-content").innerHTML = `<span class="eyebrow">${esc12(item.name)}</span><h2 style="margin-top:12px">Equipo y permisos</h2><p class="dialog-copy">Estos usuarios proceden de la cuenta conectada. Ágora no guarda sus contraseñas. ${data.authenticationConfigured ? "El canal de acceso está listo para entregar códigos." : "Falta elegir la cuenta encargada del canal de acceso."}</p><button id="sync-team" class="primary">Sincronizar equipo</button><p id="team-feedback" class="dialog-copy" role="status">${esc12(message)}</p><div class="team-list">${data.items.map((u) => `<article class="card"><h3>${esc12(u.display_name)}</h3><p>${esc12(u.email || "Sin email")}<br>Rol: ${esc12(u.role_name || u.role_type || "Sin rol")} · ${Number(u.assigned_data_only) ? "Sólo datos asignados" : "Acceso según permisos"}<br>${u.active ? "Activo" : "Ya no aparece en la cuenta"} · ${u.identityLinked ? "Identidad vinculada" : "Acceso aún no utilizado"}</p><small>${Object.entries(u.capabilities).filter(([, v]) => v === true).map(([k]) => esc12(k)).join(" · ") || "Sin módulos habilitados"}</small></article>`).join("") || '<p class="empty">Aún no hay usuarios importados.</p>'}</div>`;
        document.querySelector("#sync-team").onclick = async (e) => {
          e.currentTarget.disabled = true;
          try {
            const r = await api2(`admin/integrations/${id}/users/sync`, "POST");
            await show2(`${r.synced.count} usuarios actualizados.`);
            await renderIntegrations(container);
          } catch (error) {
            document.querySelector("#team-feedback").textContent = friendly(error) + (error.code ? " Código: " + error.code : "");
          } finally {
            const button = document.querySelector("#sync-team");
            if (button) button.disabled = false;
          }
        };
      } catch (error) {
        document.querySelector("#dialog-content").innerHTML = `<h2>Equipo y permisos</h2><p class="dialog-copy">${esc12(friendly(error))}</p>`;
      }
    }
    await show2();
  }
  function edit(item, container) {
    const dialog2 = document.querySelector("#action-dialog");
    document.querySelector("#dialog-content").innerHTML = `<span class="eyebrow">Conexión segura</span><h2 style="margin-top:12px">${item ? "Configurar subcuenta" : "Añadir subcuenta"}</h2><p class="dialog-copy">Introduce el identificador y la clave de la cuenta. La clave se guarda protegida y no se vuelve a mostrar.</p><form id="integration-form" class="dialog-form"><label>Nombre de referencia<input name="name" required maxlength="160" value="${esc12(item?.name || "")}" autocomplete="off"></label><label>Identificador de cuenta<input name="locationId" required maxlength="80" pattern="[A-Za-z0-9_-]+" value="${esc12(item?.location_id || "")}" ${item ? "readonly" : ""} autocomplete="off"></label><label>Clave de conexión ${item ? "(dejar vacío para conservar)" : ""}<input name="token" type="password" ${item ? "" : "required"} maxlength="1024" autocomplete="new-password" spellcheck="false" autocapitalize="off"></label><label>Intervalo de sincronización<select name="syncIntervalSeconds">${[60, 120, 300, 900, 3600].map((n) => `<option value="${n}" ${n === (Number(item?.sync_interval_seconds) || 120) ? "selected" : ""}>${n < 3600 ? n / 60 + " minutos" : "1 hora"}</option>`).join("")}</select></label><p id="save-feedback" class="dialog-copy" role="status"></p><button type="submit" class="primary">Guardar en el servidor</button></form>`;
    dialog2.showModal();
    const form = document.querySelector("#integration-form");
    const clearToken = () => {
      const field = form.elements.namedItem("token");
      if (field) field.value = "";
    };
    dialog2.addEventListener("close", clearToken, { once: true });
    form.onsubmit = async (e) => {
      e.preventDefault();
      const b = form.querySelector('button[type="submit"]');
      b.disabled = true;
      const data = Object.fromEntries(new FormData(form));
      data.syncIntervalSeconds = Number(data.syncIntervalSeconds);
      try {
        await api2("admin/integrations" + (item ? "/" + item.id : ""), item ? "PUT" : "POST", data);
        clearToken();
        dialog2.close();
        await renderIntegrations(container);
      } catch (e2) {
        clearToken();
        document.querySelector("#save-feedback").textContent = friendly(e2) + (e2.code ? " Código: " + e2.code + "." : " Comprueba el estado de las conexiones antes de repetir la operación.");
      } finally {
        data.token = "";
        b.disabled = false;
      }
    };
  }
  function remove(id, container) {
    const d = document.querySelector("#action-dialog");
    document.querySelector("#dialog-content").innerHTML = '<h2>Eliminar la conexión</h2><p class="dialog-copy">Se eliminará la configuración guardada de esta cuenta. Podrás volver a conectarla cuando lo necesites.</p><button id="confirm-remove" class="primary">Eliminar conexión</button><p id="remove-error" class="dialog-copy" role="status"></p>';
    d.showModal();
    document.querySelector("#confirm-remove").onclick = async () => {
      try {
        await api2(`admin/integrations/${id}`, "DELETE");
        d.close();
        await renderIntegrations(container);
      } catch (e) {
        document.querySelector("#remove-error").textContent = friendly(e);
      }
    };
  }
  window.addEventListener("agora:login", () => {
    const c = document.querySelector("#view");
    if (document.querySelector('[data-view="settings"]').classList.contains("active")) renderIntegrations(c);
  });
  function renderSetup(container, installation) {
    const labels = { runtime: "Entorno de la aplicación", origin: "Dominio seguro", encryption: "Protección de conexiones", schema: "Base de datos" };
    const canPrepare = (installation.checks || []).some((c) => c.code === "encryption_key_missing") && (installation.checks || []).every((c) => c.name === "encryption" || c.ready);
    container.innerHTML = `<div class="integration-heading"><h2>Preparación de las conexiones</h2><span class="integration-status">Ágora ${esc12(installation.version || "")}</span></div><p class="settings-notice">Hay un paso de instalación pendiente. Resuélvelo antes de introducir la clave de tu cuenta. Las actualizaciones de la base de datos se aplican solas al entrar.</p>${(installation.checks || []).map((c) => `<article class="card integration-card"><h3>${esc12(labels[c.name] || "Comprobación")}</h3><p>${c.ready ? "Correcto" : esc12(c.message)}</p>${c.code ? `<small class="danger">Código: ${esc12(c.code)}</small>` : ""}</article>`).join("")}${canPrepare ? '<button id="prepare-encryption" class="primary">Preparar protección inicial</button> ' : ""}<button id="recheck-setup" class="primary">Comprobar de nuevo</button> <button id="copy-setup" class="primary">Copiar resultado</button><p id="setup-feedback" class="dialog-copy" role="status"></p>`;
    container.querySelector("#recheck-setup").onclick = () => renderIntegrations(container);
    const prepare = container.querySelector("#prepare-encryption");
    if (prepare) prepare.onclick = async () => {
      prepare.disabled = true;
      try {
        await api2("admin/installation/encryption", "POST");
        await renderIntegrations(container);
      } catch (e) {
        container.querySelector("#setup-feedback").textContent = friendly(e);
      } finally {
        prepare.disabled = false;
      }
    };
    container.querySelector("#copy-setup").onclick = async () => {
      const summary = "Ágora " + installation.version + "\n" + (installation.checks || []).map((c) => `${c.name}: ${c.ready ? "OK" : c.code}`).join("\n");
      try {
        await navigator.clipboard.writeText(summary);
        container.querySelector("#setup-feedback").textContent = "Resultado copiado. Puedes compartirlo con soporte; no contiene claves.";
      } catch {
        container.querySelector("#setup-feedback").textContent = summary;
      }
    };
  }

  // agora/help.js
  var esc13 = (s) => String(s ?? "").replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" })[c]);
  var BASE = window.AGORA_ASSETS || "/assets/";
  var MANUAL_PDF = window.AGORA_MANUAL_PDF || "/assets/manual-vendedores.pdf";
  async function renderHelp(container) {
    const instance = /* @__PURE__ */ Symbol();
    container.agoraViewInstance = instance;
    container.innerHTML = '<div class="manual-wrap"><p class="empty">Abriendo el manual…</p></div>';
    try {
      const r = await fetch(BASE + "manual/vendedores.html", { cache: "no-cache" });
      if (!r.ok) throw new Error("missing");
      const html = await r.text();
      if (container.agoraViewInstance !== instance) return;
      const version = (document.querySelector("#app-version")?.textContent.match(/v(\d+\.\d+\.\d+)/) || [])[1] || "";
      container.innerHTML = `<div class="manual-wrap"><div class="manual-actions"><button class="secondary" type="button" onclick="window.print()">Imprimir / guardar PDF</button></div><article class="manual" id="manual">${html.replace(/\{\{version\}\}/g, esc13(version)).replace(/(src=")\/assets\//g, "$1" + BASE)}</article></div>`;
      container.querySelectorAll('.manual a[href^="#"]').forEach((a) => a.addEventListener("click", (e) => {
        e.preventDefault();
        container.querySelector(a.getAttribute("href"))?.scrollIntoView({ behavior: "smooth", block: "start" });
      }));
    } catch {
      if (container.agoraViewInstance === instance) container.innerHTML = '<div class="manual-wrap"><p class="empty">No se pudo abrir el manual ahora. Prueba de nuevo en un momento.</p></div>';
    }
  }

  // agora/maintenance.js
  var nativeFetch = window.fetch.bind(window);
  var base = () => window.AGORA_API || "/api/";
  var shown = false;
  var lastDetail = null;
  var checking = false;
  var timer2 = null;
  var isApi = (url) => String(url).includes(base());
  var isProbe = (url) => /(?:status|health)(?:\?|$)/.test(String(url));
  async function probe() {
    try {
      const r = await nativeFetch(base() + "status", { cache: "no-store" });
      if (!r.ok) {
        try { const d = await r.clone().json(); lastDetail = d && d.problema ? d : null; } catch { lastDetail = { problema: "El servidor respondió " + r.status + " en /api/status.", paso: r.status === 404 ? "La API no está enrutada: revisa que public_html/.htaccess esté subido y reinicia OpenLiteSpeed (systemctl restart lsws)." : "" }; }
        return "down";
      }
      lastDetail = null;
      const data = await r.json();
      return data.maintenance ? "paused" : "ok";
    } catch {
      return "down";
    }
  }
  function show() {
    if (shown) return;
    shown = true;
    const box = document.createElement("div");
    box.id = "maintenance";
    box.setAttribute("role", "alertdialog");
    box.setAttribute("aria-labelledby", "maintenance-title");
    box.innerHTML = `<div class="maintenance-card"><img class="maintenance-logo" src="${window.AGORA_ASSETS || "/assets/"}agora-logo.png" alt="Ágora">
    <h1 id="maintenance-title">Oops! Algo ocurre.</h1>
    <p>El servidor puede estar tomando una siesta o dándose un masaje de mantenimiento.</p>
    <p>Contacta a Ágora para más información.</p>${lastDetail ? `<details class="maintenance-detail" open><summary>Detalle para quien administra el servidor</summary><p><b>${esc13(lastDetail.problema)}</b></p>${lastDetail.paso ? `<p>${esc13(lastDetail.paso)}</p>` : ""}</details>` : ""}
    <button type="button" class="primary" id="maintenance-retry">Reintentar</button></div>`;
    document.body.append(box);
    document.body.classList.add("in-maintenance");
    document.querySelectorAll("dialog[open]").forEach((d) => {
      try {
        d.close();
      } catch {
      }
    });
    box.querySelector("#maintenance-retry").onclick = async (e) => {
      e.target.disabled = true;
      await recover();
      e.target.disabled = false;
    };
    timer2 = setInterval(recover, 15e3);
  }
  async function recover() {
    if (await probe() === "ok") location.reload();
  }
  async function check2() {
    if (shown || checking) return;
    checking = true;
    try {
      if (await probe() !== "ok") show();
    } finally {
      checking = false;
    }
  }
  function installMaintenance() {
    const maintenanceFetch = async function(input, init) {
      const url = typeof input === "string" ? input : input && input.url || "";
      if (!isApi(url) || isProbe(url)) return nativeFetch(input, init);
      let response;
      try {
        response = await nativeFetch(input, init);
      } catch (error) {
        if (navigator.onLine !== false) check2();
        throw error;
      }
      if (response.status === 502 || response.status === 504) check2();
      else if (response.status === 503) {
        response.clone().json().then((data) => {
          if (data && data.code === "maintenance") show();
          else check2();
        }).catch(() => check2());
      }
      return response;
    };
    try { window.fetch = maintenanceFetch; } catch(e) { try { Object.defineProperty(window, 'fetch', { configurable: true, enumerable: true, writable: true, value: maintenanceFetch }); } catch(err) {} }
  }
  var maintenanceShown = () => shown;

  // entry.js
  window.Agora = {
    workspace,
    friendly,
    skeleton,
    number,
    money,
    symbol,
    currencyCode,
    renderDashboard,
    periodOf,
    renderLiveInbox,
    renderContacts,
    renderBoard,
    renderWon,
    renderIntegrations,
    renderHelp,
    MANUAL_PDF,
    startWonAlert,
    wonAlertState,
    windowMarkup,
    installMaintenance,
    maintenanceShown
  };
})();
