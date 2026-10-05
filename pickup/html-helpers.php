<?php

function html_hidden_input($name, $value)
{
    return html_tag(
        "input",
        [
            "type" => "hidden",
            "name" => $name,
            "value" => $value
        ]
    );
}

function info_icon()
{
    return "<svg width='16' height='16' viewBox='0 0 16 16' aria-hidden='true'>" .
        "<circle cx='8' cy='8' r='7' fill='none' stroke='currentColor' stroke-width='1.5'/>" .
        "<circle cx='8' cy='4.6' r='1' fill='currentColor'/>" .
        "<rect x='7.25' y='7' width='1.5' height='5' fill='currentColor'/>" .
        "</svg>";
}

function html_button($text, $id, $on_click, $visible = true, $attributes = [])
{
    return html_tag(
        "button",
        [
            "type" => "button",
            "id" => $id,
            "onclick" => $on_click,
            "style" => ($visible ? "" : "display:none"),
        ] + $attributes,
        $text
    );
}

function html_symbol($image_filename, $valign = "baseline", $width = null)
{
    // vertical-align: baseline (default), text-top, text-bottom, sub, sup
    return html_tag(
        "img",
        [
            "src" => "../images/" . $image_filename,
            "style" => "vertical-align: $valign;" .
                ($width ? " max-width: " . $width . "px; height:auto;" : "")
        ]
    );
}

function html_checkbox($name, $value, $id, $onchange = "", $large = false, $checked = false)
{
    if ($large) {
        $px = 30;
        $style =
            "width: " . $px . "px; " .
            "height: " . $px . "px; " .
            "vertical-align: -40%; ";
    } else
        $style = "";

    return html_tag("input", [
        "type" => "checkbox",
        "name" => $name,
        "value" => $value,
        "id" => $id,
        "style" => $style,
        "onchange" => $onchange,
        $checked ? "checked" : ""
    ]);
}

function linkify_contacts($text)
{
    $text = htmlspecialchars($text, ENT_QUOTES, "UTF-8");

    $pattern = '/[\w.+-]+@[\w-]+\.[\w.-]+|\+?\d[\d\s\/.()-]{5,}\d/';

    return preg_replace_callback($pattern, function ($m) {
        $match = $m[0];
        if (str_contains($match, "@")) {
            return "<a href='mailto:$match'>$match</a>";
        }
        $digits = preg_replace('/\D/', '', $match);
        if (strlen($digits) < 9) // avoid linking short digit runs like dates
            return $match;
        $tel = preg_replace('/[^\d+]/', '', $match);
        return "<a href='tel:$tel'>$match</a>";
    }, $text);
}

function html_list($items, $ordered = false)
{
    if (!$items)
        return "";
    $list_type = $ordered ? "ol" : "ul";
    return "<$list_type><li>" .
        implode("</li><li>", $items) .
        "</li></$list_type>";
}

function html_index($items)
{
    $linked_items = [];
    foreach ($items as $id => $title) {
        $linked_items[] = html_tag("a", ["href" => "#$id"], $title);
    }
    return html_list($linked_items);
}

function html_select($var_name, $options, $attributes = [])
{
    $html_options = [];
    // ... '<option value="none">-- bitte ... auswählen --</option>';
    foreach ($options as $value => $text) {
        $html_options[] = "<option value='$value'>$text</option>";
    }
    return html_tag(
        "select",
        [
            "name" => $var_name,
            "id" => $var_name,
            "onChange" => "window.location.href='#'+this.value;",
        ] + $attributes,
        implode("\n", $html_options)
    );
}


function html_attribute($name, $value = "")
{
    if (is_array($value)) { // ["attr.name" => ["value1", "value2"]]
        return " $name='" . implode(" ", $value) . "'";
    } elseif (is_string($name) && $value !== "") { // ["attr.name" => "value"] 
        if (str_contains($value, '"') && str_contains($value, "'")) {
            error_log("Value for HTML attribute $name = $value contains both single and double quotes, " .
                "which is not supported.");
            return "";
        } elseif (str_contains($value, "'")) {
            return " $name=\"$value\"";
        } else {
            return " $name='$value'";
        }
    } elseif ($value) // ["attr.name"] withut value gives $name=index, $value="attr.name", like e.g. "checked" in <input type="checkbox" checked>
        return " $value";
    else
        return "";
}

function html_attributes($attributes)
{
    $html = "";
    foreach ($attributes as $name => $value) {
        $html .= html_attribute($name, $value);
    }
    return $html;
}

function html_tag($tag_name, $attributes = [], $content = null)
{
    if ($attributes == "close") {
        return "</$tag_name>";
    }
    $html = "<$tag_name";
    $html .= html_attributes($attributes);
    $html .= ">";
    if ($content !== null)
        $html .= "$content</$tag_name>";
    return $html;
}

function html_tags($tag_name, $attribute_name, $values, $attributes, $content = "")
{
    $html = "";
    foreach ($values as $value) {
        $attributes[$attribute_name] = $value;
        $html .= html_tag($tag_name, $attributes, $content);
    }
    return $html;
}
class form_input
{

    private $name;
    private $classes = [];
    private $id;
    private $value_max;
    private $value_init;
    private $value_reset;
    private $update_function = "";
    private $null_button = "";
    private $reset_button = "";
    private $clear_button = "";
    private $buttons_on_both_sides = FALSE;
    public $submit_initial_value = TRUE;
    private $data_attributes = [];


    public function set_name($name)
    {
        $this->name = $name;
        if (!$this->id)
            $this->id = strtr($name, array("[" => "-", "]" => ""));
    }

    public function add_class($class)
    {
        $this->classes[] = $class;
    }

    private function show_weight_unit()
    {
        return
            in_array("weight", $this->classes) &&
            in_array("unit", $this->classes);
    }

    public function set_id($id)
    {
        $this->id = $id;
    }

    public function get_id()
    {
        return "input-$this->id";
    }

    public function set_init_value($value_init)
    {
        if (!is_numeric($value_init))
            $value_init = 0;
        $this->value_init = $value_init;
        $this->value_reset = $value_init;
        if (in_array("number", $this->classes)) {
            $this->value_max = $value_init + 50;
        } else {
            $this->value_max = $value_init * 3; // weight
            if ($value_init == 0) {
                $this->value_max = 10000;
            }
        }
    }


    public function set_max_value($value)
    {
        $this->value_max = $value;
    }

    public function set_update_function($update_function)
    {
        $this->update_function = "updateInput(this,0," . $this->value_max . "); " . $update_function;
    }

    public function add_update_function($update_function)
    {
        $this->update_function .= " " . $update_function;
    }

    public function set_null_button($button_text = "0", $value_reset = FALSE)
    {
        // no argument, "text", or "text not received|text received"
        $this->null_button = $button_text;
        if ($value_reset !== FALSE) {
            $this->value_reset = $value_reset;
            $this->set_data_attribute("reset-value", sprintf("%.0f", $value_reset));
        }
    }

    public function set_reset_button($value_reset, $button_text = "R")
    {
        $this->value_reset = $value_reset;
        $this->reset_button = $button_text;
        $this->set_data_attribute("reset-value", sprintf("%.0f", $value_reset));
    }

    public function set_clear_button($button_text = "C")
    {
        $this->clear_button = $button_text;
    }

    public function set_buttons_on_both_sides($buttons_on_both_sides = TRUE)
    {
        $this->buttons_on_both_sides = $buttons_on_both_sides;
    }

    public function set_data_attribute($name, $value)
    {
        $this->data_attributes["data-$name"] = $value;
    }

    public function set_article_name($article_name)
    {
        $this->set_data_attribute("article", $article_name);
    }



    public function print()
    {
        print $this->html();
    }

    public function html()
    {
        $id = $this->id;
        $html_left = "";
        $html_right = "";
        $html_center = html_tag(
            "input",
            [
                "class" => $this->classes,
                "name" => $this->name,
                "id" => $this->get_id(),
                "type" => "number",
                "value" => $this->value_init,
                "min" => "0",
                "max" => $this->value_max,
                "onChange" => $this->update_function,
            ] +
            $this->data_attributes
        );
        if ($this->show_weight_unit())
            $html_center .= " Gramm ";

        if ($this->reset_button) {
            $html_left .= '<button type="button" ' .
                'id="input-' . $id . '-reset" ' .
                'onclick="my_reset(' . "'input-" . $id . "'" . ',' . $this->value_init . ')">' .
                $this->reset_button . '</button>' . "\n";
        }

        $html_left .= '<button type="button" onclick="increment(' . "'input-$id'" . ',' . $this->value_max . ')">+</button>';
        $html_right .= '<button type="button" onclick="decrement(' . "'input-$id'" . ',0)">&#8722;</button>';

        if ($this->null_button) {
            $button_texts = explode("|", $this->null_button);
            if (count($button_texts) >= 2) {
                $html_right .= "\n" . '<button type="button" ' .
                    'id="input-' . $id . '-null" ' .
                    'data-text-0="' . $button_texts[0] . '" data-text-1="' . $button_texts[1] . '" ' .
                    'onclick="zero(' . "'input-$id'" . ', this)">' . $button_texts[$this->value_init > 0 ? 0 : 1] . '</button>';
            } else {
                $html_right .= "\n" . '<button type="button" ' .
                    'onclick="zero(' . "'input-$id'" . ', false)">' . $button_texts[0] . '</button>';
            }
        }
        if ($this->clear_button) {
            $html_right .= "\n" . '<button type="button" onclick="my_clear(' . "'input-$id'" . ')">' . $this->clear_button . '</button>';
        }
        if ($this->submit_initial_value) {
            $html_right .= html_tag(
                "input",
                [
                    "type" => "hidden",
                    "name" => str_replace("[", "_initial[", $this->name),
                    "value" => $this->value_init
                ]
            );
        }
        if ($this->buttons_on_both_sides) {
            return $html_left . "\n" . $html_center . "\n" . $html_right . "\n";
        } else {
            return $html_center . "\n" . $html_left . "\n" . $html_right . "\n";
        }
    }
}

function print_table_style()
{
    return "<style>
        .pt-section { margin: 0 0 28px; }
        .pt-section-header {
            display: flex;
            flex-wrap: wrap;
            align-items: baseline;
            justify-content: space-between;
            gap: 8px;
            font-family: Ubuntu, sans-serif;
            padding: 10px 14px;
            background: #f4f4f4;
            border: 1px solid #ddd;
            border-bottom: none;
            border-radius: 8px 8px 0 0;
        }
        .pt-order-name { font-weight: 600; font-size: 1.05rem; color: #222; }
        .pt-info-wrap {
            position: relative;
            display: inline-flex;
            vertical-align: middle;
            margin-right: 6px;
            cursor: pointer;
            color: #555;
        }
        .pt-popover {
            display: none;
            position: absolute;
            top: 100%;
            left: 0;
            z-index: 10;
            background: white;
            color: #222;
            font-style: normal;
            font-weight: 400;
            font-size: 0.85rem;
            border: 1px solid #ccc;
            border-radius: 6px;
            padding: 8px 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
            width: 80vw;
            max-width: 320px;
            text-align: left;
        }
        .pt-info-btn {
            position: relative;
            display: inline-flex;
            align-items: center;
            border: none;
            background: none;
            padding: 0;
            margin: 0;
            font: inherit;
            color: inherit;
            cursor: pointer;
        }
        /* larger tap target on touch devices */
        .pt-info-btn::before { content: ''; position: absolute; inset: -10px; }
        .pt-info-wrap-right { margin-right: 0; }
        .pt-info-wrap-right .pt-info-btn { text-decoration: underline dotted; }
        .pt-info-wrap-right .pt-popover { left: auto; right: 0; }
        .pt-info-wrap.pt-open .pt-popover { display: block; }
        @media (hover: hover) {
            .pt-info-wrap:hover .pt-popover { display: block; }
        }
        .pt-popover strong { display: block; margin-bottom: 4px; }
        .pt-popover ul { list-style: none; margin: 0; padding: 0; }
        .pt-popover li { padding: 2px 0; }
        .pt-popover li.pt-pickedup { color: #1e8e3e; font-weight: 600; }
        .pt-order-link {
            position: relative;
            display: inline-flex;
            vertical-align: middle;
            margin-left: 6px;
            color: #555;
        }
        .pt-order-link:hover { color: #2f80ed; }
        /* larger tap target on touch devices */
        .pt-order-link::before { content: ''; position: absolute; inset: -10px; }
        .pt-order-date { font-size: 0.85rem; color: #666; }
        .pt-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            font-family: Ubuntu, sans-serif;
            font-size: 0.9rem;
            border: 1px solid #ddd;
            border-top: none;
            border-radius: 0 0 8px 8px;
        }
        /* round the corner cells instead of overflow: hidden, which would clip popovers */
        .pt-table tbody tr:last-child td:first-child { border-bottom-left-radius: 8px; }
        .pt-table tbody tr:last-child td:last-child { border-bottom-right-radius: 8px; }
        .pt-table th, .pt-table td {
            padding: 8px 12px;
            border: none;
            border-bottom: 1px solid #eee;
            text-align: left;
        }
        .pt-table th {
            background: #fafafa;
            font-size: 0.72rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #666;
            border-bottom: 2px solid #e0e0e0;
        }
        .pt-table td.pt-num { text-align: right; font-variant-numeric: tabular-nums; }
        .pt-table th.pt-group { text-align: center; border-bottom: 1px solid #e0e0e0; }
        .pt-table tbody tr.pt-row-even { background: #fbfbfb; }
        .pt-table tbody tr.pt-row-not-received td { color: #aaa; }
        .pt-table tbody tr:last-child td { border-bottom: none; }
        .pt-badge {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 999px;
            font-weight: 600;
            font-size: 0.82rem;
            background: #eef0f2;
            color: #555;
        }
        .pt-badge.pt-complete { background: #e3f6e8; color: #1e8e3e; }
        .pt-progress-row td { background: #fcfcfc; padding: 12px; }
        .pt-progress { display: flex; align-items: center; gap: 10px; }
        .pt-progress-track {
            flex: 1 1 auto;
            height: 10px;
            background: #e6e6e6;
            border-radius: 6px;
            overflow: hidden;
        }
        .pt-progress-fill {
            height: 100%;
            border-radius: 6px;
            background: #2f80ed;
            transition: width 0.3s ease;
        }
        .pt-progress-fill.pt-complete { background: #27ae60; }
        .pt-progress-label {
            flex: 0 0 auto;
            font-weight: 600;
            font-size: 0.85rem;
            color: #444;
            min-width: 3.2em;
            text-align: right;
        }
    </style>
    <script>
        // toggles the ordergroups popovers on click/tap (hover alone doesn't work on touch devices)
        document.addEventListener('click', function (event) {
            var button = event.target.closest('.pt-info-btn');
            var wrap = button ? button.closest('.pt-info-wrap') : null;
            if (!wrap && event.target.closest('.pt-popover')) return;
            document.querySelectorAll('.pt-info-wrap.pt-open').forEach(function (el) {
                if (el !== wrap) {
                    el.classList.remove('pt-open');
                    el.querySelector('.pt-info-btn').setAttribute('aria-expanded', 'false');
                }
            });
            if (wrap) {
                var is_open = wrap.classList.toggle('pt-open');
                button.setAttribute('aria-expanded', is_open ? 'true' : 'false');
            }
        });
    </script>";
}

function print_table_percent_class($percent)
{
    return $percent >= 100 ? "pt-complete" : "pt-incomplete";
}

function print_table_label($key)
{
    $labels = [
        "article_name" => "Artikel",
        "ordered" => "bestellt",
        "received" => "erhalten",
        "pickup_percent" => "%",
        "pickup" => "gesamt",
        "pickup_count" => "Bestellgruppen",
    ];
    return $labels[$key] ?? ucfirst(str_replace("_", " ", $key));
}

function print_table_ordergroups_popover($ordergroups, $trigger_html = null, $align_right = false)
{
    // popover listing the ordergroups (picked up ones highlighted), opened by
    // hovering or tapping $trigger_html (defaults to the info icon)
    if (!$ordergroups) {
        return $trigger_html ?? "";
    }
    $items = array_map(function ($group) {
        $name = htmlspecialchars($group["name"] ?? "", ENT_QUOTES, "UTF-8");
        $class = !empty($group["pickedup"]) ? " class='pt-pickedup'" : "";
        return "<li$class>$name</li>";
    }, $ordergroups);

    $wrap_class = "pt-info-wrap" . ($align_right ? " pt-info-wrap-right" : "");
    // icon-only buttons need a label for screen readers
    $aria_label = $trigger_html === null ? " aria-label='Bestellgruppen anzeigen'" : "";
    return "<span class='$wrap_class'>" .
        "<button type='button' class='pt-info-btn' aria-expanded='false' title='Bestellgruppen'$aria_label>" .
        ($trigger_html ?? info_icon()) .
        "</button>" .
        "<div class='pt-popover'><strong>Bestellgruppen:</strong><ul>" . implode("", $items) . "</ul></div>" .
        "</span>";
}

function print_table_order_link($url)
{
    // external link icon to the order in foodsoft
    if (!$url) {
        return "";
    }
    return "<a class='pt-order-link' href='" . htmlspecialchars($url, ENT_QUOTES, "UTF-8") . "' " .
        "target='_blank' rel='noopener' title='Bestellung in Foodsoft öffnen' aria-label='Bestellung in Foodsoft öffnen'>" .
        "<svg width='16' height='16' viewBox='0 0 16 16' aria-hidden='true' fill='none' stroke='currentColor' " .
        "stroke-width='1.5' stroke-linecap='round' stroke-linejoin='round'>" .
        "<path d='M9 2.5h4.5V7'/><path d='M13.5 2.5L7 9'/>" .
        "<path d='M11.5 9.5v3a1 1 0 0 1-1 1h-7a1 1 0 0 1-1-1v-7a1 1 0 0 1 1-1h3'/>" .
        "</svg></a>";
}

function print_table_header_group($keys)
{
    // groups the trailing pickup_percent/pickup/pickup_count columns under a
    // shared "Abholung" spanning header, if present in that exact order
    $group_keys = ["pickup_percent", "pickup", "pickup_count"];
    return array_slice($keys, -count($group_keys)) === $group_keys ? $group_keys : [];
}

function print_table_format_date($date_str)
{
    $date = date_create($date_str);
    if (!$date) {
        return htmlspecialchars($date_str, ENT_QUOTES, "UTF-8");
    }
    $weekdays = ["Mon" => "Mo", "Tue" => "Di", "Wed" => "Mi", "Thu" => "Do", "Fri" => "Fr", "Sat" => "Sa", "Sun" => "So"];
    return strtr(date_format($date, "D d.m.Y"), $weekdays);
}

function print_table_format_value($key, $value)
{
    if (is_array($value)) {
        return htmlspecialchars(implode(", ", array_filter($value)), ENT_QUOTES, "UTF-8");
    }
    if (str_contains($key, "percent")) {
        $percent = round(floatval($value));
        return "<span class='pt-badge " . print_table_percent_class($percent) . "'>$percent%</span>";
    }
    if (is_numeric($value)) {
        return htmlspecialchars(rtrim(rtrim(sprintf("%.2f", $value), "0"), "."), ENT_QUOTES, "UTF-8");
    }
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}

function print_table_format_pickup_count($article)
{
    $pickup_count = $article["pickup_count"] ?? 0;
    if (!isset($article["grouporders_count"])) {
        return (string) $pickup_count;
    }
    return $pickup_count . "/" . $article["grouporders_count"];
}

function print_table_keys($article)
{
    // article fields shown as columns; the rest are only used inside other cells
    return array_values(array_diff(array_keys($article), ["grouporders_count", "ordergroups"]));
}

function print_table_progress_percent($articles)
{
    $articles_received = array_filter($articles, fn($article) => $article['received'] > 0);
    $total_pickedup_percent = 0;
    foreach ($articles_received as $article) {
        $total_pickedup_percent += $article["pickup_percent"] ?? 0;
    }
    return $total_pickedup_percent > 0 ? round($total_pickedup_percent / count($articles_received)) : 0;
}

function print_article_row($article, $keys, $is_even = false)
{
    if (!isset($keys)) {
        $keys = print_table_keys($article);
    }
    $not_received = isset($article["received"]) && floatval($article["received"]) == 0;
    $row_classes = array_filter([$is_even ? "pt-row-even" : "", $not_received ? "pt-row-not-received" : ""]);
    $row_class = $row_classes ? " class='" . implode(" ", $row_classes) . "'" : "";
    print "<tr$row_class>";
    foreach ($keys as $key) {
        if ($key === "pickup_count") {
            print "<td class='pt-num'>" .
                print_table_ordergroups_popover(
                    $article["ordergroups"] ?? [],
                    htmlspecialchars(print_table_format_pickup_count($article), ENT_QUOTES, "UTF-8"),
                    true
                ) .
                "</td>";
            continue;
        }
        if ($not_received && str_contains($key, "percent")) {
            print "<td class='pt-num'></td>";
            continue;
        }
        $value = $article[$key] ?? "";
        $class = is_numeric($value) ? " class='pt-num'" : "";
        print "<td$class>" . print_table_format_value($key, $value) . "</td>";
    }
    print "</tr>";
}

function print_summary_table($sections)
{
    // renders $sections (one per order, each with order_name, order_pickup and
    // a list of articles) as a set of modern-styled html tables, with a
    // progress bar summarizing the order's overall pickup completion
    static $style_printed = false;
    if (!$style_printed) {
        print print_table_style();
        $style_printed = true;
    }

    if (!$sections) {
        print html_tag("p", ["class" => "info"], "Keine Bestellungen zum Anzeigen.");
        return;
    }

    foreach ($sections as $section) {
        $articles = $section["articles"] ?? [];
        $keys = $articles ? print_table_keys($articles[0]) : [];

        print "<section class='pt-section'>";
        print "<div class='pt-section-header'>";
        print "<span class='pt-order-name'>" .
            print_table_ordergroups_popover($section["ordergroups"] ?? []) .
            htmlspecialchars($section["order_name"] ?? "", ENT_QUOTES, "UTF-8") .
            print_table_order_link($section["order_url"] ?? "") . "</span>";
        if (!empty($section["order_pickup"])) {
            print "<span class='pt-order-date'>" . print_table_format_date($section["order_pickup"]) . "</span>";
        }
        print "</div>";

        if (!$articles) {
            print "<table class='pt-table'><tr><td>Keine Artikel zum Anzeigen.</td></tr></table>";
            print "</section>";
            continue;
        }

        print "<table class='pt-table'>";
        print "<thead>";
        $group_keys = print_table_header_group($keys);
        print "<tr>";
        foreach ($keys as $key) {
            if (in_array($key, $group_keys)) {
                if ($key === $group_keys[0]) {
                    print "<th colspan='" . count($group_keys) . "' class='pt-group'>Abholung (App)</th>";
                }
                continue;
            }
            print "<th" . ($group_keys ? " rowspan='2'" : "") . ">" .
                htmlspecialchars(print_table_label($key), ENT_QUOTES, "UTF-8") . "</th>";
        }
        print "</tr>";
        if ($group_keys) {
            print "<tr>";
            foreach ($group_keys as $key) {
                print "<th>" . htmlspecialchars(print_table_label($key), ENT_QUOTES, "UTF-8") . "</th>";
            }
            print "</tr>";
        }
        print "</thead>";

        print "<tbody>";

        $percent = print_table_progress_percent($articles);
        $complete_class = print_table_percent_class($percent);
        print "<tr class='pt-progress-row'>";
        print "<td colspan='" . count($keys) . "'>";
        print "<div class='pt-progress'>";
        print "<div class='pt-progress-track'><div class='pt-progress-fill $complete_class' style='width:{$percent}%'></div></div>";
        print "<span class='pt-progress-label'>$percent%</span>";
        print "</div>";
        print "</td>";
        print "</tr>";

        foreach ($articles as $i => $article) {
            print_article_row($article, $keys, $i % 2 === 1);
        }
        print "</tbody>";
        print "</table>";
        print "</section>";
    }
}

?>
