function before_unload() {
    return "Bist du sicher, dass du diese Seite verlassen möchtest? Deine Änderungen werden nicht gespeichert!";
}

function check_form() {
    var n = 0;
    for (const e of document.getElementsByClassName('ready-for-pickup')) {
        if (!e.checked) n++;
    }
    if (n == 0) return true;
    return window.confirm(n + ' abholbereite Artikel nicht abgehakt - ' +
        'versehentlich oder bewusst? ' +
        'Nicht abgehakte Artikel werden beim Abrechnen genauso von deinem ' +
        'Guthaben abgezogen wie abgehakte. ' +
        'Wenn du dir sicher bist, dass Artikel nicht geliefert wurden, ' +
        'gehe mit `Abbrechen` nochmal zurück, setze die Zahl für Stück ' +
        'bzw. Gewicht bei `erhalten:` mit `nicht erhalten` auf Null ' +
        'und hake den Artikel ab, im Sinne von `ist für mich erledigt`. ' +
        'Wenn du die nicht abgehakten Artikel später abholst, mach weiter mit `Ok`.');
}

function get_number(string) { // pull out first integer number from string like "input-weight-37" => 37
    return parseInt(string.match(/\d+/)[0]);
}

//let init_function_name = "init";
function init() {
    for (const article of document.getElementsByClassName("article")) {
        const id = get_number(article.id);
        if (document.getElementById("note-textarea-" + id).value) {
            show_note(id, true);
        }
        update_received(id);
        update_weight(id);
    }
    update_article_visibilities(1);
}


function update_article_visibility(id) {
    // if (document.getElementById("checkbox-" + id).checked &&
    //     document.getElementById("p-article-" + id).classList.contains("week2+"))
    //     show_article(id, false);

    // for testing only:
    // update_article_visibilities();
}


function update_article_visibilities(n_weeks) {
    const visible_order_items = new Map();
    const visible_pickup_items = new Map();
    for (const article of document.getElementsByClassName("article")) {
        const id = get_number(article.id);
        if (document.getElementById("checkbox-" + id).checked &&
            article.classList.contains("week2+"))
            article.style = style_display(true, n_weeks > 1);

        const order_id = article.getAttribute('data-order-id');
        let n = visible_order_items.get(order_id) || 0;
        visible_order_items.set(order_id, n + (article.style.getPropertyValue("display") == "none" ? 0 : 1));

        const pickup_date = article.getAttribute('data-pickup-date-index');
        n = visible_pickup_items.get(pickup_date) || 0;
        visible_pickup_items.set(pickup_date, n + (article.style.getPropertyValue("display") == "none" ? 0 : 1));
    }

    for (const [order_id, n] of visible_order_items) {
        //console.log(order_id + " " + n);
        for (const e of document.getElementsByClassName("order-" + order_id)) {
            e.style = style_display(false, n == 0);
        }
    }
    for (const [pickup_date, n] of visible_pickup_items) {
        //console.log(pickup_date + " " + n);
        document.getElementById("date-" + pickup_date).style = style_display(false, n == 0);
    }
}

function show_articles(n_weeks) {
    document.getElementById("show-more").style = style_display(false, n_weeks > 1);
    document.getElementById("show-less").style = style_display(true, n_weeks > 1);
    update_article_visibilities(n_weeks);
}




function style_display(display, show_it) {
    return display && show_it || !display && !show_it ? "" : "display:none";
}


function show_article(id, show_it) {
    document.getElementById("p-article-" + id).style = style_display(true, show_it);
}

function show_individual_weight_inputs(id, n) {
    document.getElementById("weight-separated-" + id).style.display = "none";
    for (let i = 0; i < n; i++) {
        document.getElementById("single-weight-" + id + "-" + i).style.display = "";
    }
}

function calculate_sum(id, n) {
    let sum = 0;
    for (let i = 0; i < n; i++) {
        let v = document.getElementById("weight-" + id + "-" + i).value;
        let x = 0;
        if (v.length > 0)
            x = parseInt(v);
        //console.log("Gewicht " + (i + 1) + ": '" + v + "'isnan: " + isNaN(v) + " int: " + x + " length: " + v.length);
        /* 
           v    isNaN(v) x    isNan(x)
           ------------------------------
           ''   false    NaN  true
           11   false    11   false
           1x   true     1    false
           xx   true     NaN  true
        */

        if (isNaN(x) || isNaN(v)) {
            //console.log("Gewicht " + (i + 1) + ": '" + v + "' ist keine gültige Zahl");
            alert("Gewicht " + (i + 1) + ": '" + v + "' ist keine gültige Zahl, bitte korrigieren!");
            x = 0;
        }
        sum += x;
    }
    document.getElementById("input-weight_received-" + id).value = sum;
}

function show_note(id, show_it) {
    const article = document.getElementById("p-article-" + id);
    if (article) {
        document.getElementById("note-" + id).style = style_display(true, show_it);;
        document.getElementById("note-button-show-" + id).style = style_display(false, show_it);
        document.getElementById("note-button-hide-" + id).style = style_display(true, show_it);
    }
}

function update_received(id) {
    // console.log("update_received " + id);
    const received = document.getElementById("input-received-" + id);
    if (received !== null) {
        const article = document.getElementById("p-article-" + id);
        set_class(article, 'changed', received.value != received.getAttribute('data-ordered'));
        set_class(article, 'disabled', received.value == 0);
    }
}

function update_weight(id) {
    const weight_received = document.getElementById("input-weight_received-" + id);
    if (weight_received !== null) {
        const article = document.getElementById("p-article-" + id);
        set_class(article, 'changed', weight_received.value != weight_received.getAttribute('data-weight-ordered'));
        set_class(article, 'disabled', weight_received.value == 0);
    }
}

function set_class(element, class_name, set_it) {
    if (set_it)
        element.classList.add(class_name);
    else
        element.classList.remove(class_name);
}

function toggle_comment_popover(order_id) {
    var popover = document.getElementById('comment-popover-' + order_id);
    var is_open = popover.style.display === 'block';
    document.querySelectorAll('.comment-popover').forEach(function (el) {
        el.style.display = 'none';
    });
    popover.style.display = is_open ? 'none' : 'block';
}

document.addEventListener('pointerdown', function (event) {
    if (event.target.closest('.info-icon, .comment-popover')) return;
    document.querySelectorAll('.comment-popover').forEach(function (el) {
        el.style.display = 'none';
    });
});

// close the hamburger menu when tapping outside of it
document.addEventListener('pointerdown', function (event) {
    document.querySelectorAll('.app-menu[open]').forEach(function (menu) {
        if (!menu.contains(event.target)) menu.removeAttribute('open');
    });
});




