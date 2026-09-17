<?php
require_once("article.php");

class ArticleDistribute extends Article
{
    public $grouporders = [];
    public $n_grouporders;
    private $input_total_id;

    public function __construct($order, $data)
    {
        parent::__construct($order, $data);
        $this->ordered = 0;
        $this->received = 0;
        foreach ($data["grouporders"] as $grouporder) {
            $go = [
                "name" => $grouporder["ordergroup_name"],
                "ordered" => intval($grouporder["ordered"]),
                "tolerance" => intval($grouporder["tolerance"]),
                "received" => floatval($grouporder["received"])
            ];
            // replace the next 5 lines if status is obtained directly
            $this->tolerance = $go["tolerance"];
            $this->set_state();
            $go["checked"] = $this->is_pickedup;
            $go["distributed"] = $this->is_distributed;
            $go["tolerance"] = $this->tolerance;

            if ($this->has_weight) {
                foreach (["ordered", "received"] as $q) {
                    $go["weight_$q"] = round($go[$q] * $this->unit_weight);
                }
            }

            $this->grouporders[$grouporder["id"]] = $go;

            $this->ordered += $go["ordered"];
            $this->received += $go["received"];
        }
        $this->n_grouporders = count($this->grouporders);
        $this->finalize_construct();
    }

    public function html_name()
    {
        print html_tag("h3", ["id" => "article-$this->id"], $this->name);
        parent::html_name();
    }

    public static function html_href($id, $text)
    {
        return html_tag(
            "a",
            ["href" => "#article-$id"],
            html_tag("button", [], $text)
        );
    }

    public function html_ordered()
    {
        print "<p>Bestellt: " . $this->ordered . unit_str($this->unit) . " "; // " Einheiten ";
        if ($this->has_weight) {
            print " = " . weight_str($this->weight_ordered);
        }
        print ", <b>";
        if ($this->has_adaptable_weight) {
            print $this->app->local_currency_str($this->price_per_kg) . "/kg ";
        } else {
            print $this->app->local_currency_str($this->price) . "/Einheit ";
        }
        print "</b>";
        // $deposit = strpos($article["name"], "Pfand") !== FALSE ? " und Pfand" : "";
        // print " <small>inkl. Mwst.$deposit</small>";
        print "</p>";
    }


    public function html_received()
    {
        $not_delivered = "nicht geliefert|doch geliefert";
        print "<p> ";
        if ($this->app->edit_received) {
            $input = new form_input();
            $input->add_update_function("ajaxOnChange(this);");
            $input->add_update_function("update_received(this);");
            // $input->set_data_attribute("n-ordergroups", $this->n_grouporders);
            $input->set_buttons_on_both_sides();
            if ($this->has_adaptable_weight) {
                print "Gewicht erhalten: ";
                $input->set_name($this->var_name("weight_received"));
                $input->set_init_value($this->weight_received);
                $input->add_class("weight");
                $input->set_clear_button();
                $input->set_null_button($not_delivered, $this->weight_received);
                $input->set_reset_button($this->weight_received);
                $input->set_article_name($this->name);
                $input->print();
                print " Gramm";

            } else {
                print "erhalten: ";
                $input->set_name($this->var_name("received"));
                $input->set_init_value($this->received);
                $input->add_class("number");
                $input->set_null_button($not_delivered, $this->received);
                $input->set_reset_button($this->received);
                $input->set_article_name(sprintf("%d x %s", $this->received, $this->name));
                $input->print();
            }
        } else {

        }
        print "</p>";
        return $input->get_id();
    }

    public function html_article_notes()
    {
        $this->html_note_for_balancing(
            "Notiz fürs Abrechnen",
            "Hinweis fürs Abrechnen, wenn z.B. am Lieferschein etwas nicht korrekt ist (geht nicht an Besteller*innen):"
        );

        if ($this->n_grouporders > 1) {
            $this->html_note_for_all(
                "Notiz für alle eingeben",
                "Hinweis an alle, die diesen Artikel bestellt haben:"
            );
        }
    }

    public function html_note_for_all($button, $text)
    {
        print "<p>";
        $this->html_note($button, $text, true, false);
        print "</p>";
    }

    public function html_note_for_balancing($button, $text)
    {
        print "<p>";
        $this->html_note($button, $text, true, true);
        print "</p>";
    }


    public function html_buttons($input_id)
    {
        if ($this->n_grouporders > 1) {
            print html_tag(
                "p",
                [],
                html_button(
                    "&ddarr; alle 'erhalten' zurück setzen",
                    "button-reset-$this->id",
                    "update_received('$input_id', 'reset');"
                )
            );
            if ($this->has_adaptable_weight) {
                print html_tag(
                    "p",
                    [],
                    html_button(
                        "&ddarr; Gesamtgewicht auf Gruppen aufteilen",
                        "button-distribute-$this->id",
                        "update_received('$input_id', 'distribute-total');"
                    )
                );
                print html_tag(
                    "p",
                    [],
                    html_button(
                        "&uuarr; Gesamtgewicht von Gruppen übernehmen",
                        "button-sum-$this->id",
                        "update_received('$input_id', 'update-sum');"
                    )
                );
            } else {
                print html_tag(
                    "p",
                    [],
                    html_button(
                        "&uuarr; Gesamtanzahl von $this->n_grouporders Gruppen übernehmen",
                        "button-sum-$this->id",
                        "update_received('$input_id', 'update-sum');"
                    )
                );
            }
        }

    }
    public function html_group_orders()
    {
        $table = [];
        foreach ($this->grouporders as $id => $grouporder) {
            $received = $grouporder["received"];
            $weight_received = $grouporder["weight_received"] ?? 0;
            if ($this->app->edit_received) {
                $input = new form_input();
                $input->add_class("article-$this->id");
                $input->set_data_attribute("article-id", $this->id);
                $input->set_data_attribute("order-id", $this->order->id);
                $input->add_update_function("ajaxOnChange(this);");
                $input->add_update_function("update_received(this);");
                $input->set_buttons_on_both_sides();
                if ($this->has_adaptable_weight) {
                    $input->set_name("weight_received_grouporder[$id]");
                    $input->set_init_value($weight_received);
                    $input->set_data_attribute("received", $weight_received);
                    $input->set_data_attribute("unit_weight", $this->unit_weight);
                    $input->add_class("weight");
                    $weight_received = $input->html();
                } else {
                    $input->set_name("received_grouporder[$id]");
                    $input->set_init_value($received);
                    $input->set_data_attribute("received", $received);
                    $input->add_class("number");
                    $received = $input->html();
                }
            }
            $table[] = [
                "tr_id" => "tr-$id",
                "checkbox" => html_checkbox(
                    "distributed[]",
                    $id,
                    "checkbox-$id",
                    "ajaxOnChange(this);",
                    true,
                    $this->is_distributed
                ),
                "ordered" => $grouporder["ordered"],
                "received" => $received,
                "weight_ordered" => $grouporder["weight_ordered"] ?? "",
                "weight_received" => $weight_received,
                "group_name" => $grouporder["name"],
                "note" => $this->html_grouporder_note($id, $grouporder["name"])
            ];

        }
        $this->app->html_table($table, array_filter([
            "checkbox" => " ",
            "ordered" => "bestellt",
            "weight_ordered" => $this->has_adaptable_weight ? " " : "",
            "received" => $this->has_adaptable_weight ? "" : "erhalten",
            "weight_received" => $this->has_adaptable_weight ? "Gramm erhalten" : "",
            "group_name" => "Bestellgruppe",
            "note" => "Notiz",
        ]), ["received" => $this->has_adaptable_weight ? "%.2f" : "%.0f"]);
    }


    private function html_grouporder_note($id, $ordergroup)
    {
        $grouporder_id = "grouporder-$id";
        return html_button(
            html_tag("img", ["src" => "../images/notiz.png"]), //"Notiz", // "Notiz eingeben"
            "note-button-show-$grouporder_id",
            "show_note('$grouporder_id', true)"
        ) .
            html_tag(
                "span",
                ["id" => "note-$grouporder_id", "style" => "display:none"],
                html_tag("span", ["class" => "info"], "Hinweis für Bestellgruppe:") .
                "<br>" .
                html_tag("textarea", [
                    "type" => "text",
                    "id" => "note-textarea-$grouporder_id",
                    "name" => "note_grouporder[$id]",
                    "rows" => 3,
                    "cols" => 28,
                    "data-id" => $grouporder_id,
                    "data-order-id" => $this->order->id,
                    "data-article-name" => $this->name,
                    "data-ordergroup" => $ordergroup,
                    "onchange" => "ajaxOnChange(this);",
                ], "") .
                "<br>" .
                html_button(
                    'Notiz verbergen',
                    "note-button-hide-$grouporder_id",
                    "show_note('$grouporder_id', false)"
                )
            );

        // $this->html_hidden_input("note_initial", $this->note);
    }

    public function html_difference()
    {
        if ($this->n_grouporders > 1) {
            print html_tag("p", [], "Differenz gesamt &minus; Summe einzeln: " .
                html_tag("input", [
                    "class" => 'weight',
                    "type" => 'number',
                    "value" => '0',
                    "disabled" => 'disabled',
                    "id" => "input-diff-$this->id",
                ]));
        }
    }

    public function html_update_received($input_id)
    {
        if ($this->n_grouporders > 1) {
            print html_tag("script", [], "update_received('$input_id');");
        }
    }
}
?>