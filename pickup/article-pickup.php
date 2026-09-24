<?php
require_once "article.php";
class ArticlePickup extends Article
{
    public $article_grouporder_ids;
    public $ordered_before = 0;
    public $received_before = 0;
    public $weight_received_before;
    public $ordered_remaning;
    public $received_remaning;
    public $weight_remaning;
    public $weight_recommended;

    public function __construct($order, $data)
    {
        parent::__construct($order, $data);
        $this->ordered = intval($data["ordered"]);
        $this->tolerance = intval($data["tolerance"]);
        $this->received = floatval($data["received"]);

        $this->grouporder_article_id = $data["grouporder_article_id"] ?? null; # 216734
        $this->ordered_total = $data["ordered_total"] ?? null; # [] => 3
        $this->received_total = $data["received_total"] ?? null; #  [] => 
        $this->article_grouporder_ids = $this->app->article_grouporder_ids[$this->grouporder_article_id] ?? [];

        $this->set_state();
        $this->has_variable_weight = str_contains(
            $this->name . $this->unit,
            $this->app->variable_weight_tag
        );

        $this->finalize_construct(); // set weights, ...

        foreach ($this->article_grouporder_ids as $id) {
            $article = $this->app->articles_pickedup[$id];
            if ($article["pickedup"]) {
                $this->ordered_before += $article["ordered"];
                $this->received_before += $article["received"];
            }
        }
        if ($this->unit_weight > 0) {
            $this->weight_received_before = round($this->received_before * $this->unit_weight);

            $this->ordered_remaning = $this->ordered_total - $this->ordered_before;
            $this->received_remaning = $this->received_total - $this->received_before;
            $this->weight_remaning = $this->weight_received_total - $this->weight_received_before;
            if ($this->ordered_remaning != 0)
                $this->weight_recommended = $this->ordered * ($this->weight_remaning / $this->ordered_remaning);
            else {
                $this->weight_recommended = 0;
            }
        }
    }

    public function html_form()
    {
        $classes = [
            "article",
        ];
        if ($this->not_received)
            $classes[] = "disabled";
        elseif ($this->has_variable_weight)
            $classes[] = "mandatory";
        if ($this->order->week >= 2)
            $classes[] = "week2+";
        if ($this->order->state == "closed")
            $classes[] = "closed";
        print html_tag("p", [
            "class" => $classes,
            "id" => "p-article-" . $this->id,
            "data-order-id" => $this->order->id,
            "data-pickup-date-index" => $this->order->pickup_date_index,
        ]); {
            $this->html_checkbox();
            $this->html_name();
            print "<br>\n";

            $this->html_unit_and_price();
            print "<br>\n";

            $this->html_ordered();
            $this->html_received();
            print "<br>\n";

            $this->html_note("Notiz eingeben", "Hinweis zur Abrechnung:");

            $this->html_distributed();

            $this->html_hidden_input("order_article_ids[" . $this->order->id . "][]", "ID");
            if ($this->is_distributed)
                $this->html_hidden_input("distributed[]", "ID");

            $this->html_hidden_input("grouporder_article_id", $this->grouporder_article_id);
        }
        print "</p>";
    }


    private function html_checkbox()
    {
        $classes = ["checkbox"];
        if ($this->order->ready_for_pickup)
            $classes[] = "ready-for-pickup";
        print html_tag("input", [
            "type" => "checkbox",
            "class" => $classes,
            "name" => "checked[]",
            "id" => "checkbox-" . $this->id,
            "value" => $this->id,
            "onChange" => "update_article_visibility(" . $this->id . ")",
            ($this->is_pickedup ? "checked" : ""),
        ]);
        if ($this->is_pickedup) {
            // print " <input type='hidden' name='checked_initial[]' value='" . $this->id . "'>\n";
            print html_tag(
                "input",
                [
                    "type" => "hidden",
                    "name" => 'checked_initial[]',
                    "value" => $this->id
                ]
            );
        }
    }

    public function html_name()
    {
        // print "<b>" . $this->name . "</b>";
        print html_tag("b", [], $this->name);
        parent::html_name();
    }


    private function html_unit_and_price()
    {
        print "Einheit: " . $this->unit;
        if ($this->price)
            print " zu " . $this->price_str;
        if ($this->deposit) {
            print $this->price ? " + " : ", ";
            print "Pfand " . $this->app->local_currency_str(abs($this->deposit)) . " ";
        }
        if (
            $this->unit_weight > 0 &&
            $this->price > 0 &&
            $this->unit_weight != 1000
        ) {
            print " (" . $this->app->local_currency_str($this->price_per_kg) . "/kg)";
        }
        $this->html_hidden_input("unit", $this->unit);
        $this->html_hidden_input("unit_weight", $this->unit_weight);
        $this->html_hidden_input("price", $this->price);
    }

    public function html_ordered()
    {
        print $this->order->ordered_term . ": " . $this->ordered;
        $this->html_hidden_input("ordered", $this->ordered);
        $this->html_hidden_input("tolerance", $this->tolerance);
        if ($this->unit_weight > 0) {
            $this->html_hidden_input("weight_ordered", $this->weight_ordered);
        } elseif ($this->unit_volume && $this->order->show_volume) {
            printf(" (%s)", volume_str($this->ordered * $this->unit_volume));
        }
    }

    private function html_received()
    {
        // $this->html_hidden_input("received_initial", $this->received);
        // included in input class

        if ($this->order->is_open)
            return; // no adaptions for open orders

        print ", ";
        $this->html_hidden_input("has_variable_weight", $this->has_variable_weight);
        if ($this->order->is_closed) {
            print "abgerechnet: " . $this->received;
            if ($this->has_variable_weight) {
                print ", " . $this->weight_received . " Gramm";
            }
            return;
        }

        if ($this->has_adaptable_weight) {
            if ($this->adapted_received)
                print "erhalten: " . $this->received;
            print "<br>";
            print "Gewicht bestellt: " . $this->weight_ordered . " Gramm<br>";
            $this->html_weight_input();
        } else {
            $this->html_number_input();
            // if ($this->has_adaptable_weight) {
            //     $this->html_optional_weight_input();
            // }
        }

        // if ($this->unit_weight > 0) {
        //     $this->html_hidden_input("weight_received_initial", $this->weight_received);
        // }
        // included in input class

        // todo: warning if pickup is in future
    }



    private function html_optional_weight_input()
    {
        $button_id = "button-weight-optional-" . $this->id;
        $weight_received_id = "weight-received-" . $this->id;
        $weight_input_id = "weight-adaption-" . $this->id;
        $on_click =
            "document.getElementById('$weight_input_id').style.display = ''; " .
            "document.getElementById('$button_id').style.display = 'none'; ";
        print "<br><span id='$weight_received_id'>";
        if ($this->adapted_received) {
            print "Gewicht erhalten: " . $this->weight_received . " Gramm ";
            $on_click .= "document.getElementById('$weight_received_id').style.display = 'none'; ";
        } else {
            print "Gewicht bestellt: " . $this->weight_ordered . " Gramm ";
        }
        print html_button("abweichendes Gewicht eingeben", $button_id, $on_click);
        print "<br></span>";
        $this->html_weight_input("display:none");
    }

    private function html_weight_input($style = "")
    {
        if ($style) {
            print "<span style='$style' id='weight-adaption-" . $this->id . "'>";
        }
        print "Gewicht erhalten: ";
        $input = new form_input(); # Gewichtsabweichung
        $input->set_name($this->var_name("weight_received"));
        $input->set_init_value($this->weight_received);
        $input->set_data_attribute("weight-ordered", $this->weight_ordered);
        $input->set_max_value($this->weight_ordered * 5);
        $input->add_class("weight unit");
        $input->set_update_function("update_weight($this->id)");
        $input->set_null_button($this->text_not_received, $this->reset_weight);
        $input->set_article_name(sprintf("%s", $this->name));
        //$input->set_buttons_on_both_sides();
        $input->print();


        if ($this->ordered > 1 && $this->has_variable_weight) {

            print "<br>";
            print html_button(
                "Gewicht für jedes Stück extra eingeben",
                "weight-separated-$this->id",
                "show_individual_weight_inputs($this->id, $this->ordered)"
            );
            for ($i = 0; $i < $this->ordered; $i++) {
                $idnr = $this->id . "-$i";
                print html_tag(
                    "span",
                    [
                        "id" => "single-weight-$idnr",
                        "style" => "display: none"
                    ],
                    sprintf("Gewicht Stück %d: ", $i + 1) .
                    html_tag("input", [
                        "class" => "weight",
                        "type" => "text",
                        "name" => "single_weight[" . $this->id . "][$i]",
                        "id" => "weight-$idnr",
                        "size" => "2",
                        "onChange" => "calculate_sum($this->id,$this->ordered)",
                    ]) . " Gramm<br>"
                );
            }
        }
        if ($style) {
            print "</span>";
        }
    }

    private function html_number_input()
    {
        print "erhalten: ";
        $input = new form_input(); # Anzahl Artikel
        $input->set_name($this->var_name("received"));
        $input->set_init_value($this->received);
        $input->set_data_attribute("ordered", $this->ordered);
        $input->add_class("number");
        $input->set_update_function("update_received(" . $this->id . ")");
        $input->set_null_button($this->text_not_received, $this->reset_received);
        $input->set_article_name(sprintf("%g x %s", $this->reset_received, $this->name));
        //$input->set_buttons_on_both_sides();
        $input->print();
    }

    private function html_distributed()
    {
        if ($this->is_distributed) {
            print "<br>" . html_tag(
                "i",
                [],
                html_symbol("kistl.png", "baseline", 14) . " " .
                "Eingekistlt" .
                " von " . $this->distributed_by .
                " am " . $this->distributed_at .
                ($this->distribution_note ? " Hinweis: " . $this->distribution_note : "")
            );
        } elseif ($this->distribution_note) {
            print "<br>" . html_tag(
                "i",
                [],
                "Hinweis vom Einkistln" .
                " von " . $this->distributed_by .
                " am " . $this->distributed_at . ": " .
                $this->distribution_note
            );
        }
    }
}
?>