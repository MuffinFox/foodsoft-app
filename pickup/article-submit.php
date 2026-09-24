<?php
require_once "article.php";
class ArticleSubmitted extends Article
{
    public $update = [];
    public $single_weights;
    public $changelog_items; // array of strings for note entries

    public function __construct($order, $id)
    {
        $this->order = $order;
        $this->app = $order->app;
        $this->id = $id;
        $this->name = $this->get("article_name");
        $this->has_variable_weight = $this->get("has_variable_weight");
        $this->ordered = floatval($this->get("ordered"));
        $this->received = $this->get("received");
        if ($this->received === null) {
            $this->received = $this->ordered;
        } else {
            $this->received = floatval($this->received);
        }
        $this->unit = $this->get("unit");
        $this->unit_weight = floatval($this->get("unit_weight"));
        $this->price = floatval($this->get("price"));

        $this->weight_ordered = floatval($this->get("weight_ordered"));
        $this->weight_received = floatval($this->get("weight_received"));

        $this->single_weights = $this->get("single_weight") ?: [];
        $this->note = $this->get("note");
        $this->changelog_items = [];

        $this->grouporder_article_id = $this->get("grouporder_article_id") ?: null;
    }

    public function get($property)
    {
        return $this->app->post[$property][$this->id] ?? null;
    }

    public function is_checked()
    {
        return in_array($this->id, $this->app->post["checked"] ?? []);
    }
    public function was_checked()
    {
        return in_array($this->id, $this->app->post["checked_initial"] ?? []);
    }
    public function checked_has_changed()
    {
        return $this->is_checked() != $this->was_checked();
    }

    public function is_distributed()
    {
        return in_array($this->id, $this->app->post["distributed"] ?? []);
    }


    public function has_changed($property, $digits = null)
    {
        return $this->is_different($property, $property . "_initial", $digits);
    }

    public function is_different($property1, $property2, $digits = null)
    {
        $id = $this->id;
        $post = $this->app->post;
        if (!isset($post[$property1][$id]) || !isset($post[$property2][$id])) {
            return false; // if one of the values is not set, we consider it as not different
        }
        $value1 = $post[$property1][$id];
        $value2 = $post[$property2][$id];
        if ($digits === null) {
            return $value1 != $value2;
        } else {
            $difference = abs(round($value1, $digits) - round($value2, $digits));
            $diff_min = pow(10, -$digits);
            return $difference > $diff_min;
        }
    }
    public function is_equal($property1, $property2, $digits = null)
    {
        return !$this->is_different($property1, $property2, $digits);
    }


    public function update_status_if($condition)
    {
        if (!$condition)
            return;
        if ($this->app->article_state_save_method == "foodsoft-db-tolerance") {  //, "foodsoft-db-article-state", "in-app"
            // use tolerance to save pickup information
            $this->update["tolerance"] =
                ($this->get("tolerance") ?? 0) +
                ($this->is_distributed() ? $this->app->base_distribution : 0) +
                ($this->is_checked() ? $this->app->base_pickedup : 0);
        } elseif ($this->app->article_state_save_method == "foodsoft-db-article-state") {
            // use database field
            // $update["state"] = ...
            // todo: implement it, create database columns
            error_log("update_status(): noch nicht implementierte Methode: " . $this->app->article_state_save_method);
        } elseif ($this->app->article_state_save_method == "in-app") {
            $this->app->articles_pickedup[] = [
                "id" => $this->id,
                "pickedup" => $this->is_checked(),
                "date" => date("Y-m-d H:i:s"),

                "grouporder_article_id" => $this->grouporder_article_id,
                "ordered" => $this->ordered,
                "received" => $this->received,
                "weight_received" => $this->weight_received,
            ];
        } else {
            error_log("update_status(): unbekannte Methode: " . $this->app->article_state_save_method);
        }
    }

    public function update_received_if($condition)
    {
        if ($condition) {
            $this->update["result"] = $this->received;
            $this->add_changelog_received();
        }
    }

    public function update_received_weight_if($condition)
    {
        if ($condition) {
            $this->update["result"] = $this->received =
                $this->weight_received / $this->unit_weight;
            $this->add_changelog_received_weight();
        }
    }


    public function add_changelog_received()
    {
        $received_numbers = [$this->ordered];
        if ($this->is_different("received_initial", "ordered")) {
            $received_numbers[] = $this->get("received_initial");
        }
        $received_numbers[] = $this->get("received");
        $received_numbers = implode(" => ", $received_numbers);
        $this->changelog_items[] = $received_numbers;
    }

    public function add_changelog_received_weight()
    {
        $weight_numbers = [$this->weight_ordered];
        if ($this->is_different("weight_received_initial", "weight_ordered", 0)) {
            $weight_numbers[] = $this->get("weight_received_initial");
        }
        $received = $this->weight_received;
        if ($this->single_weights) {
            $received .= " (" . implode("+", $this->single_weights) . ")";
        }
        $weight_numbers[] = $received;
        $weight_numbers = implode(" g => ", $weight_numbers) . " g";
        $this->changelog_items[] = $weight_numbers;
    }

    public function has_changelog_items()
    {
        return count($this->changelog_items) > 0;
    }

    public function add_user_note_if($condition)
    {
        if ($condition) {
            // alt:
            // $this->note_items[] = "@ " . $this->get("note") . " #" . $this->id;

            // neu: ... @123456 Kommentar zum Artikel


            if ($this->app->comment_level == 2) {
                $this->changelog_items[] = "@" . $this->id . " " . $this->note;
            } else {
                if ($this->app->comment_level == 1) {
                    $this->changelog_items[] = $this->note;
                }
                $this->app->articles_pickedup[] = [
                    "id" => $this->id,
                    "note" => $this->note,
                    "date" => date("Y-m-d H:i:s")
                ];
            }
        }
    }

    public function changelog_entry()
    {
        return $this->has_changelog_items() &&
            ($this->app->comment_level >= 2 || $this->has_changed("note")) ?
            $this->name . ": " . implode(" ", $this->changelog_items) :
            "";
    }

    public function html_order_and_article_name()
    {
        return
            $this->order->date_end . " " . $this->order->producer . "<br>" .
            "<b>" . $this->name . "</b><br>";
    }

    public function html_changes()
    {
        $html = "";
        if ($this->has_changelog_items()) { // has_notes -> changes were submitted
            $price_ordered = $this->get("price") * $this->get("ordered");
            $price_received = $this->get("price") * $this->received;
            $price_difference = $price_received - $price_ordered;

            $html = html_tag(
                "p",
                ["class" => "article"],  //"<p class='article'>";

                $this->html_order_and_article_name() .

                "bestellt: " . implode(" ", $this->changelog_items) . " erhalten.<br>" .

                "Preis: " .
                $this->app->local_currency_str($price_ordered) . " => " .
                $this->app->local_currency_str($price_received) . " " .
                "(" . $this->app->local_currency_str($price_difference, $plus_sign = true) . ")" .
                ".<br>" .

                ($this->note ? "Notiz zum Abrechnen: " . $this->note : "")
            );

        } elseif (
            $this->has_variable_weight &&
            !$this->order->is_closed && // if order is closed, weight_ordered and weight_received are not submitted!
            $this->is_equal("weight_ordered", "weight_received", 0)
        ) {
            // no different weight submitted!
            $price_ordered = $this->get("price") * $this->get("ordered");

            $html = html_tag(
                "p",
                ["class" => ["article", "warning"]],    // <p class='article warning'>";

                html_tag(
                    "span",
                    ["class" => "info"],
                    "Achtung, du hast kein Gewicht eingegeben. " .
                    "Wenn das Gewicht so stimmt, kannst du diese Warnung ignorieren:"
                ) . "<br>" .

                $this->html_order_and_article_name() .
                "bestellt: " .
                ($this->ordered > 1 ? $this->ordered . " x " . $this->unit_weight . " g = " : "") .
                $this->weight_ordered . ".<br>" .

                "Preis: " . $this->app->local_currency_str($price_ordered) . ".<br>" .

                ($this->note ? "Notiz zum Abrechnen: " . $this->note : "")
            );
        } elseif ($this->has_changed("note")) {
            // no changes in number or weight submitted, but note!
            $html = html_tag(
                "p",
                [],
                $this->html_order_and_article_name() .
                "bestellt: " . $this->ordered . " => " . $this->received . " erhalten." . "<br>" .
                "Notiz zum Abrechnen: " . $this->note
            );
        }
        return $html;
    }

    public function html_unchecked()
    {
        if ($this->is_checked())
            return "";
        $html = $this->order->date_end . " " . $this->order->producer . ": <nobr>";
        $html .= $this->received . " x <b>" . $this->name . "</b></nobr>";
        return $html;
    }

}
?>