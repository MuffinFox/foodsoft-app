<?php
require_once("order.php");
class OrderDistribute extends Order
{
    public $distribute;
    public $merge_articles;
    public $article_index = [];

    public function __construct($app, $data)
    {
        parent::__construct($app, $data);
        $this->distribute = $this->parameters["distribute"] ?? false; // @pickup:{"distribute":true}
        $this->merge_articles = $this->parameters["merge"] ?? []; // @pickup:{"merge":[["..",".."], ...]}
        $this->sort_articles();
        $this->set_article_index();
    }

    public function sort_articles()
    {
        $names = array_column($this->articles, 'name');
        foreach ($names as $i => $name) {
            $name = strtr($name, array("Ä" => "Az", "Ö" => "Oz", "Ü" => "Uz"));
            $j = 0;
            while (!ctype_alpha($name[$j]) && $j < strlen($name))
                $j++;
            $names[$i] = substr($name, $j);
        }
        array_multisort(
            $names,
            SORT_ASC,
            $this->articles
        );
    }
    public function set_article_index()
    {
        // after: $this->sort_articles();
        foreach ($this->articles as $article) {
            $first_letter = strtoupper(mb_substr($article["name"], 0, 1));
            if (!array_key_exists($first_letter, $this->article_index))
                $this->article_index[$first_letter] = $article["id"];
        }
    }

    public function create_article($article_data)
    {
        return new ArticleDistribute($this, $article_data);
    }

    public function merge_articles()
    {
        foreach ($this->articles as &$article) {

        }
    }

    public function name()
    {
        return $this->producer . " " . $this->date_str;
    }

    public function html_heading()
    {
        print html_tag(
            "h2",
            ["id" => $this->heading_id()],
            $this->name(),
        );
    }





    public function html_article_index($min_articles)
    {
        if (count($this->article_index) < $min_articles)
            return;
        $a = [html_tag("a", ["href" => "#"], html_tag("button", [], "&#8679;"))];
        foreach ($this->article_index as $first_letter => $article_id) {
            $a[] = ArticleDistribute::html_href($article_id, $first_letter);
        }
        print html_tag("p", [], implode(" ", $a));
        // "style" => 'font-size:24px;'
    }
}