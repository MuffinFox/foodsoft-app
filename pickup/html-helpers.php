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
