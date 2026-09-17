function show_more_orders() {
    console.log("show_more_orders");
    document.getElementById("show-more").style = "display:none";
    // let i = 0;
    for (const e of document.querySelectorAll(".past-order")) {
        // unvollständig: document.getElementsByClassName("past-order")
        // console.log(i++ + e.tagName + ": " + e.innerHTML);
        e.classList.remove("past-order");
    }
}

function id_article(element) {
    if (element.id.includes("grouporder"))
        return element.getAttribute('data-article-id');
    else // input-weight_received-218962
        return element.id.split("-")[2];
}

function is_grouporder(element) {
    return element.id.includes("grouporder");
}

function is_total(element) {
    return !is_grouporder(element);
}

function id_grouporder_article(element) {
    return element.id.split("-")[2];
}


function set_value(id, value) {
    const e = document.getElementById(id);
    if (e != undefined) {
        e.value = value;
        return true;
    } else {
        return false;
    }
}

function get_int_value(id, default_value) {
    const e = document.getElementById(id);
    if (e != undefined) {
        return parseInt(e.value);
    } else {
        return default_value;
    }
}

function disable(id, disable) {
    const e = document.getElementById(id);
    if (e) e.disabled = disable;
}

function get_data(element, name) {
    return element.getAttribute("data-" + name)
}

function set_class(id, classname, activate) {
    const element = document.getElementById(id);
    if (element == undefined) return;
    if (activate) {
        element.classList.add(classname);
    } else {
        element.classList.remove(classname);
    }
}

function update_received(element, action = "none") {
    if (typeof element === "string") {
        element = document.getElementById(element);
    }
    const article_id = id_article(element);
    const is_weight = element.id.includes("weight");
    const total_id = "input-" + (is_weight ? "weight_" : "") + "received-" + article_id;
    let total = get_int_value(total_id, 0);
    //console.log("update_received(" + element.id + ", " + action + "): article_id=" + article_id + ", total_id=" + total_id + ", total= " + total);
    const grouporders = document.getElementsByClassName("article-" + article_id);
    const n_grouporders = grouporders.length;

    let grouporder_sum = 0;
    if (action == "distribute-total") {
        for (const e of grouporders) {
            grouporder_sum += parseFloat(get_data(e, "received"));
        }
        const entity = total / grouporder_sum;
        console.log("  entity=" + entity);
        for (const e of grouporders) {
            e.value = Math.round(parseFloat(get_data(e, "received") * entity));
            ajaxOnChange(e);
        }
        grouporder_sum = total;
    } else {
        for (const e of grouporders) {
            if (action == "reset") {
                e.value = get_data(e, "received");
                ajaxOnChange(e);
            }
            grouporder_sum += Number(e.value);
        }
        if (action == "update-sum") {
            set_value(total_id, grouporder_sum);
            ajaxOnChange(document.getElementById(total_id));
            total = grouporder_sum;
        }
        if (is_total(element)) {
            if (action == "ajax") {
                update_zero_button(element.id);
            } else {
                if (element.value == 0 && grouporder_sum > 0) {
                    // nicht erhalten
                    for (const e of grouporders) {
                        e.value = 0;
                        ajaxOnChange(e);
                    }
                    grouporder_sum = 0;
                } else if (element.value > 0 && grouporder_sum == 0) {
                    // doch erhalten
                    for (const e of grouporders) {
                        e.value = get_data(e, "received");
                        ajaxOnChange(e);
                        grouporder_sum += Number(e.value);
                    }
                }
            }
            for (const e of grouporders) {
                set_class("tr-" + id_grouporder_article(e), 'notreceived', e.value == 0);
                // console.log("tr-" + id_grouporder_article(e) + ' notreceived: ' + (e.value == 0) + " " + e.value);
            }
        } else {
            set_class("tr-" + id_grouporder_article(element), 'notreceived', element.value == 0);
        }
    }
    // console.log("   grouporder_sum=" + grouporder_sum);



    if (n_grouporders > 1) {
        const input_diff_id = "input-diff-" + article_id;
        const difference = total - grouporder_sum;
        set_value(input_diff_id, difference);
        document.getElementById(input_diff_id).style.backgroundColor =
            Math.abs(difference) > (is_weight ? 10 : 0) ? "yellow" : "white";
        for (const button of ["distribute", "sum"]) { // "reset",
            disable("button-" + button + "-" + article_id, difference == 0);
            // console.log("button-" + button + "-" + article_id + ": " + (difference == 0));
        }
    } else if (n_grouporders == 1) {
        if (is_grouporder(element)) {
            set_value(total_id, element.value);
            ajaxOnChange(document.getElementById(total_id));
        } else {
            grouporders[0].value = element.value;
            ajaxOnChange(grouporders[0]);
        }
    }

}







// ==== ajax for checkbox and received input sync ==========================================

function page_log(text) {
    // document.getElementById("log").innerHTML += text;
}


function ajaxOnChange(element) {
    const xmlhttp = new XMLHttpRequest();
    let value = "";

    let add_grouporder_ids = false;
    console.log("   tag:" + element.tagName + " element type: " + element.type + ", value: " + element.value);
    if (element.type == "checkbox") {
        value = element.checked; // ? true : false);
        console.log("   element type: " + element.type + ", checked: " + value);
    } else if (element.type == "number") {
        value = Number(element.value);
        console.log("   element type: " + element.type + ", value: " + value);
        add_grouporder_ids = !element.id.includes("grouporder");
    } else if (element.type == "textarea") {
        value = element.value;
        console.log("   element type: " + element.type + ", value: " + value);
        add_grouporder_ids = !element.id.includes("grouporder") && !element.id.includes("balancing");
    } else {
        console.log("   unknown element type: " + element.type + ", value: " + value);
    }

    const data = {
        date: currentDateAndTimeString(),
        session_id: window.my_session_id,
        username: window.username,
        element_id: element.id,
        value: value,
    };

    const order_id = get_data(element, "order-id");
    if (order_id) data.order_id = order_id;

    const article_name = get_data(element, "article-name");
    if (article_name) data.article_name = article_name;

    const unit_weight = get_data(element, "unit_weight");
    if (unit_weight) data.received = value / unit_weight;

    const ordergroup = get_data(element, "ordergroup");
    if (ordergroup) data.ordergroup = ordergroup;

    if (add_grouporder_ids) {
        // deterorder_idine grouporder_ids of input elements for individual ordergroups
        data.grouporder_ids = [];
        const article_id = id_article(element);
        for (const e of document.getElementsByClassName("article-" + article_id)) {
            // id: input-weight_received_grouporder-2619460
            data.grouporder_ids.push(Number(id_grouporder_article(e)));
        }
        console.log("  grouporder_ids with article-" + article_id + ": " + data.grouporder_ids);
    }

    let url = "?" +
        [
            "app=distribute",
            "action=ajax-write",
            "ajax-data=" + JSON.stringify(data)
        ].join("&");
    // console.log("get url with join: " + url);
    page_log(currentDateAndTimeString() + " " + url + "\n");
    xmlhttp.open("GET", url, true);
    xmlhttp.onreadystatechange = function () {
        page_log(xmlhttp.readyState + "w ");
        // check if the request is complete
        if (xmlhttp.readyState === 4) {
            if (xmlhttp.status === 200) {
                // Request was successful
                page_log("Success: " + xmlhttp.responseText + "\n");
            } else {
                // Request failed
                page_log("Error: " + xmlhttp.status + " " + xmlhttp.statusText + "\n");
            }
        }
    };
    xmlhttp.send();
}

function save_changes() {
    const xmlhttp = new XMLHttpRequest();
    const data = {
        date: currentDateAndTimeString(),
        session_id: window.my_session_id,
        username: window.username,
        save: true,
    };
    const params = new URLSearchParams(window.location.search);
    const access_token = params.get("access_token");
    const url = "?" +
        [
            "app=distribute",
            "action=ajax-save",
            "ajax-data=" + JSON.stringify(data),
            "access_token=" + access_token,
        ].join("&");
    // console.log("save url: " + url);
    xmlhttp.open("GET", url, true);
    xmlhttp.onreadystatechange = function () {
        // check if the request is complete
        if (xmlhttp.readyState === 4) {
            if (xmlhttp.status === 200) {
                // Request was successful
                console.log("save: " + xmlhttp.responseText + "\n");
            }
        }
    };
    xmlhttp.send();
}



function sleep(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
}

async function update(xmlhttp) {
    let sleeptime = 0;
    window.unprocessed_response = true;
    let i = 0;
    while (true) {
        await sleep(1000); // 1000 ms = 1 Sekunde
        document.getElementById("sync-status").innerHTML =
            window.n_ajax_empy_responses + " " +
            sleeptime + "/" + window.ajax_timeout + " " +
            window.n_ajax_events;
        sleeptime += 1;
        // console.log(`update ${i}: ${window.n_ajax_events} events, sleeptime ${sleeptime}, ${window.ajax_idle_time} s idle`);
        if (window.unprocessed_response || sleeptime > window.ajax_timeout + 10) { // sleeptime >= seconds
            // console.log(`update ${i} ${seconds} s: ${window.n_ajax_events} events, ${t} s idle`);
            if (window.n_ajax_empy_responses * window.ajax_timeout >= 600) {
                if (!window.confirm(currentDateAndTimeString() + ": es ist seit mehr als 10 Minuten keine Aktivität mehr erfolgt. Ok um weiterzumachen, Abbrechen/Cancel um neu anzufangen.")) {
                    window.onbeforeunload = null;
                    window.location = document.referrer;
                    break;
                }
                window.n_ajax_empy_responses = 0;
            }
            window.unprocessed_response = false;
            load_events(xmlhttp, window.n_ajax_events);
            sleeptime = 0;
        }
        i++;
    }
}


function start_update(username, ajax_timeout) {
    console.log("start_update('" + username + "')");

    // window.ajax_filename = filename;
    window.n_ajax_events = 0;
    window.n_ajax_requests = 0;
    window.n_ajax_empy_responses = 0;
    window.my_session_id = Math.floor(Math.random() * 100000);
    window.username = username;
    window.ajax_timeout = ajax_timeout;

    load_events(null, -1); // events from previous weeks, excluding current
    window.n_ajax_events = 0;
    load_events(null, 0); // events from current week, sets window.n_ajax_events

    const xmlhttp = new XMLHttpRequest()
    xmlhttp.onreadystatechange = function () {
        page_log(xmlhttp.readyState + "r ");
        // console.log("onreadystatechange: " + xmlhttp.readyState);
        if (xmlhttp.readyState == 4) {
            if (xmlhttp.status == 200) {
                window.unprocessed_response = true;
                page_log("Success: " + xmlhttp.responseText + "\n");
                // console.log("onreadystatechange: process_ajax_response: " + xmlhttp.responseText + "\n");
                process_ajax_response(xmlhttp.responseText, window.my_session_id);
            } else {
                // Request failed
                // console.log("onreadystatechange: error: status" + xmlhttp.status + " " + xmlhttp.statusText + "\n");
                page_log("Error: " + xmlhttp.status + " " + xmlhttp.statusText + "\n");
            }
        }
    };
    update(xmlhttp); // start automatic updates
}

function load_events(xmlhttp = null, from_event = -2) {
    // from_event = -2: all events from last weeks inluding current, 
    // from_event = -1: all events from last weeks excluding current, 
    // from_event >= 0: current week only
    // console.log("load_events(from_event: " + from_event + ")");
    if (xmlhttp === null) {
        console.log("  creating xmlhttp");
        xmlhttp = new XMLHttpRequest();
        xmlhttp.onreadystatechange = function () {
            if (xmlhttp.readyState == 4 && xmlhttp.status == 200) {
                process_ajax_response(xmlhttp.responseText, null);
            }
        };
    }
    let qs =
        [
            "app=distribute",
            "action=ajax-read",
            "from_event=" + from_event,
        ].join("&");
    // console.log("  sending " + qs);
    xmlhttp.open("GET", "?" + qs, true);
    xmlhttp.send();
}



function process_ajax_response(response, my_session_id) {
    const events = response.split("\n").filter(n => n); // filter: Leerzeilen entfernen
    if (events.length == 0) {
        window.n_ajax_empy_responses++;
    }
    else {
        window.n_ajax_empy_responses = 0;
    }
    // page_log("process_ajax_response: n events: " + n + " n_ajax_empy_responses: " + window.n_ajax_empy_responses + "\n");
    // console.log("process_ajax_response: events: " + events.length + "\n" + response);
    let i = 0;
    for (const event of events) {
        const d = JSON.parse(event);

        // element of d used here: 
        //   element_id: element.id,
        //   value: value,
        //   session_id: window.my_session_id,

        // not here used:
        //   date: currentDateAndTimeString(),
        //   username: window.username,
        //   grouporder_ids

        if (my_session_id === null) {
            // document.getElementById("sync-status").innerHTML = i + "/" + n;
        }
        if (d.session_id == my_session_id) {
            // ignore events from own session: no need to update form element
            // console.log("ignoriert: " + checkbox_id)
            continue;
        }
        if (d.element_id) {
            // update form elements from changes in other sessions
            const e = document.getElementById(d.element_id);
            // console.log("processing element " + (++i) + "/" + events.length + " id " + d.element_id);
            if (e) {
                console.log("  element id " + d.element_id + " tagName: " + e.tagName + " type: " + e.type + " => " + d.value)
                if (e.tagName == "INPUT") {
                    if (e.type == "checkbox") {
                        e.checked = d.value;
                    }
                    else if (e.type == "number") {
                        e.value = d.value;
                        // console.log("  setting " + e.id + " to " + e.value); // ", onChange: ");
                        update_received(e, "ajax");
                    } else {
                        console.log("  *** unknown type: " + e.type);
                        // e.innerHTML = d.value.trim();
                    }
                } else if (e.tagName == "TEXTAREA") {
                    e.value = d.value;
                    show_note(get_data(e, "id"), false); // Notiz nicht aufklappen, aber gelb hervorheben
                } else {
                    console.log("  *** unknown tagName: " + e.tagName);
                }
            }
            else console.log("getElementById failed with " + d.element_id);
        }
        if (my_session_id) {
            window.n_ajax_events += events.length;
        }
    }
    if (my_session_id == null) {
        document.getElementById("sync-status").innerHTML = "ready";
    }
}


function currentDateAndTimeString() {
    const currentDate = new Date();
    return currentDate.toLocaleString("de-AT"); // required format: "20.12.2012, 03:00:00"
}

function style_display(display, show_it) {
    return display && show_it || !display && !show_it ? "" : "display:none; ";
}

function show_note(id, show_it) {
    const has_text = document.getElementById("note-textarea-" + id).value.length > 0
    document.getElementById("note-" + id).style = style_display(true, show_it);
    document.getElementById("note-button-show-" + id).style = style_display(false, show_it) + (has_text ? "background-color: #FFC;" : "");
    document.getElementById("note-button-hide-" + id).style = style_display(true, show_it);
}