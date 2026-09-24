<?php

class Article
{
    public $app = null;
    public $data;
    public $order;
    public $id;
    public $name;
    public $price;
    public $price_str;
    public $deposit;
    public $ordered;
    public $tolerance;
    public $received;
    public $not_received;
    public $reset_received;
    public $adapted_received;
    public $unit;
    public $unit_weight;
    public $unit_volume;

    public $grouporder_article_id;
    public $ordered_total;
    public $received_total;

    public $price_per_kg;
    public $weight_ordered;
    public $weight_ordered_total;
    public $weight_received;
    public $weight_received_total;
    public $reset_weight;
    public $has_weight;
    public $has_variable_weight;
    public $has_adaptable_weight;
    public $has_locked_weight;
    public $locked_weight;
    public $is_pickedup;
    public $is_distributed;

    public $note;

    public $distributed_by;
    public $distributed_at;
    public $distribution_note;

    public $text_not_received = "nicht erhalten|doch erhalten";

    public function __construct($order, $data)
    {
        if ($order) { // for tests, $order can be null
            $this->order = $order;
            $this->app = $order->app;
        }
        $this->data = $data;

        $this->id = $data["id"] ?? null;
        $this->name = $data["name"] ?? null;

        $this->price = floatval($data["price"] ?? "0");
        $this->price_str = $this->app ? $this->app->local_currency_str($this->price) : sprintf("%.2f €", $this->price);
        $this->deposit = floatval($data["deposit"] ?? "0");

        $this->set_unit_weight($data["unit"]);
        $this->set_unit_volume($data["unit"]);
        $this->set_locked_weight($this->app->locked_weight_tags ?? []);
        if ($order) { // for tests, $order can be null
            $this->has_adaptable_weight =
                $this->has_weight &&
                $this->order->has_adaptable_weights &&
                !$this->has_locked_weight;

            $this->note = $order->article_comments[$this->id] ??
                $this->app->article_notes[$this->id] ?? "";
        }
    }


    public function set_state()
    {
        // there are multiple supported ways to save the articles' state,
        // all of them are supported in reading data, but may be overwritten by the later ones

        // $app->article_state_save_method == "foodsoft-db-tolerance"
        // use tolerance to save pickup information
        $tolerance_db = $this->tolerance;
        $this->tolerance = $tolerance_db % 1000;
        $this->is_distributed = intdiv($tolerance_db, $this->app->base_distribution) % 10 == 1;
        $this->is_pickedup = intdiv($tolerance_db, $this->app->base_pickedup) % 10 == 1;


        // $app->article_state_save_method ==  "foodsoft-db-article-state"
        // // alternative: use database field
        // todo: implemet it ...

        // $app->article_state_save_method == "in-app"
        $this->is_distributed = $this->app->articles_distributed[$this->id]["distributed"] ??
            $this->is_distributed;
        $this->distributed_by = $this->app->articles_distributed[$this->id]["by"] ?? "?";
        if ($date = $this->app->articles_distributed[$this->id]["date"] ?? "") {
            $dt = DateTime::createFromFormat('j.n.Y, H:i:s', $date);
            $this->distributed_at = ($dt !== false ? $dt->format('j.n. \u\m H:i') : "?");
        } else {
            $this->distributed_at = "?";
        }
        $this->distribution_note = $this->app->article_distribution_notes[$this->id] ?? "";

        $this->is_pickedup = $this->app->articles_pickedup[$this->id]["pickedup"] ??
            $this->is_pickedup;
        $this->is_pickedup |= !$this->is_received(); // mark it as "done" if not received
    }

    public function finalize_construct()
    {
        $this->not_received = ($this->received == 0);
        $this->reset_received = $this->received ?: $this->ordered;
        $this->adapted_received = ($this->received != $this->ordered);

        $this->weight_ordered = $this->unit_weight * $this->ordered;
        $this->weight_received = round($this->unit_weight * $this->received);
        $this->reset_weight = round($this->unit_weight * $this->reset_received);

        $this->weight_ordered_total = $this->unit_weight * $this->ordered_total;
        $this->weight_received_total = round($this->unit_weight * $this->received_total);
    }

    public function is_received()
    {
        return $this->received > 0;
    }

    private function set_locked_weight($tags)
    {
        $this->has_locked_weight = FALSE;
        foreach ($tags as $tag) {
            if (str_contains($this->unit, $tag)) {
                $this->has_locked_weight = TRUE;
                return;
            }
        }
    }
    private function set_unit_weight($unit)
    {
        // set unit weight (weight of one article entity) in gram from foodsoft unit-string
        // e.g. "Stück <1,3 kg"=> 1300 (g)
        $this->unit = $unit;
        $unit_lc = strtolower(trim($unit));
        if (preg_match('/(\d+(?:[.,]\d+)?)\s*kg\b/i', $unit, $m)) {
            // 2,5 kg => 2500
            $this->unit_weight = 1000 * floatval(str_replace(',', '.', $m[1]));
        } elseif (preg_match('/(\d+(?:[.,]\d+)?)\s*g\b/i', $unit, $m)) {
            // 250 g => 250
            $this->unit_weight = floatval(str_replace(',', '.', $m[1]));
        } elseif ($unit_lc == "kg" || str_contains($unit_lc, "kilopreis")) {
            $this->unit_weight = 1000;
        } else {
            $this->unit_weight = 0;
        }
        // printf("[%.0f g]", $this->unit_weight);
        if ($this->has_weight = ($this->unit_weight > 0))
            $this->price_per_kg = $this->price / $this->unit_weight * 1000;
    }

    private function set_unit_volume($unit)
    {
        // set unit volume (volume of one article entity) in ml from foodsoft unit-string
        // e.g. "250 ml"=> 250 (ml)

        if (preg_match('/(\d+(?:[.,]\d+)?)\s*ml\b/i', $unit, $m)) {
            // 250 ml => 250
            $this->unit_volume = floatval(str_replace(',', '.', $m[1]));
        } elseif (
            preg_match('/(\d+(?:[.,]\d+)?)\s*[lL]\b/i', $unit, $m) ||
            preg_match('/(\d+(?:[.,]\d+)?)\s*Liter\b/i', $unit, $m)
        ) {
            // 1 L => 1000
            $this->unit_volume = 1000 * floatval(str_replace(',', '.', $m[1]));
        } else {
            $this->unit_volume = 0;
        }
    }

    public function var_name($var)
    {
        return $var . "[" . $this->id . "]";
    }

    public function html_hidden_input($varname, $value)
    {
        if ($value == "ID")
            $value = $this->id;
        else
            $varname = $this->var_name($varname);

        print "<input type='hidden' name='$varname' value='$value'>";
    }


    public function html_name()
    {
        $this->html_hidden_input("article_name", $this->name);
    }

    public function html_note($button, $text, $ajax_onchange = false, $for_balancing = false)
    {
        // $text = "Hinweis zur Abrechnung:";
        $id = $this->id;
        if ($for_balancing) {
            $id = "balancing-$id";
        }
        if ($this->order->state == "closed")
            $text .= " (Änderungen in erhaltener Menge können nicht mehr " .
                "berücksichtigt werden, weil die Bestellung bereits abgerechnet ist!)";
        print html_button(
            $button, // "Notiz eingeben"
            "note-button-show-$id",
            "show_note('$id', true)"
        );
        print html_tag(
            "span",
            ["id" => "note-$id", "style" => "display:none"],
            html_tag("span", ["class" => "info"], $text) .
            "<br>" .
            html_tag("textarea", [
                "type" => "text",
                "id" => "note-textarea-$id",
                "name" => $this->var_name($for_balancing ? "note_balancing" : "note"),
                "rows" => 3,
                "cols" => 28,
                "data-order-id" => $this->order->id,
                "data-article-name" => $this->name,
                "data-id" => $id,
                "onchange" => $ajax_onchange ? "ajaxOnChange(this);" : "",
            ], $this->note) .
            "<br>" .
            html_button(
                'Notiz verbergen',
                "note-button-hide-$id",
                "show_note('$id', false)"
            )
        );
        $this->html_hidden_input("note_initial", $this->note);


        // print '<button type="button" ' .
        //     ' onclick="show_note(' . $this->id . ',true)" ' .
        //     ' id="note-button-show-' . $this->id . '">' .
        //     'Notiz eingeben' .
        //     '</button>';
        // print '<span id="note-' . $this->id . '" style="display:none">';
        // print '<span class="info">' . $text . '</span><br>';
        // print '<textarea type="text" ' .
        //     ' id="note-textarea-' . $this->id . '" ' .
        //     ' name="' . $this->var_name(" note") . '" ' . 'rows=3 cols=28>' . $this->note .
        //     '</textarea><br>';
        // print '<button type="button" ' .
        //     ' onclick="show_note(' . $this->id . ', false)" ' .
        //     ' id="note-button-hide-' .
        //     $this->id .
        //     '">' .
        //     'Notiz verbergen' .
        //     '</button>';
        // print '</span>';
    }
}
