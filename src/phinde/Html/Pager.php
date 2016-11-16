<?php
namespace phinde;

/**
 * A better result pager.
 *
 * Rules:
 * - "Prev" and "next" buttons are outside
 * - No "first" and "last", but "1" and "$totalNumOfPages"
 * - two previous and two next pages are shown as buttons
 * - When current page is <= 5, first 5 pages are shown
 * - ".." is only shown for at least two skipped pages
 *
 * Examples:
 * [<< prev] [1] [2] [3] [next >>]
 * [<< prev] [1] [2] [3] [4] [5] [next >>]
 * [<< prev] [1] [2] [3] [4] [5] ... [8] [next >>]
 * [<< prev] [1] ... [4] [5] [6] [7] [8] ... [10] [next >>]
 *
 * replace ".." with actual link when between previous and next is only one
 */
class Html_Pager
{
    protected $pager;

    /**
     * Create a new pager
     *
     * @param integer $itemCount   Number of items in total
     * @param integer $perPage     Number of items on one page
     * @param integer $currentPage Current page, beginning with 1
     * @param string  $filename    URL the page number shall be appended
     */
    public function __construct($itemCount, $perPage, $currentPage, $filename)
    {
        $append = true;
        if (strpos($filename, '%d') !== false) {
            $append = false;
        }

        $numPages = ceil($itemCount / $perPage);
        $this->numPages = $numPages;

        //1-based
        $pages = [
            1 => true,
            2 => true,
            $numPages - 1 => true,
            $numPages     => true,
        ];
        if ($currentPage <= 6) {
            $pages[3] = 3;
            $pages[4] = 4;
            $pages[5] = 5;
        }
        if ($currentPage >= $numPages - 5) {
            $pages[$numPages - 2] = true;
            $pages[$numPages - 3] = true;
            $pages[$numPages - 4] = true;
        }
        for ($n = $currentPage - 2; $n <= $currentPage + 2; $n++) {
            $pages[$n] = true;
        }
        foreach (array_keys($pages) as $key) {
            if ($key < 1 || $key > $numPages) {
                unset($pages[$key]);
            }
        }
        if ($currentPage >= 7 && !isset($pages[4])) {
            $pages[3] = null;
        }
        if ($currentPage <= $numPages - 6 && !isset($pages[$numPages - 3])) {
            $pages[$numPages - 2] = null;
        }

        ksort($pages);
        foreach ($pages as $pageNum => &$value) {
            if ($pageNum == $currentPage) {
                $value = ['active'=> false, 'title' => $pageNum];
            } else if ($value !== null) {
                $value = $this->makeLink($pageNum, $filename);
            } else {
                $value = ['active'=> false, 'title' => '…'];
            }
        }

        $prev = ['active'=> false, 'title' => '« prev'];
        if ($currentPage > 1) {
            $prev = $this->makeLink($currentPage - 1, $filename, '« prev');
        }
        $next = ['active'=> false, 'title' => 'next »'];
        if ($currentPage < $numPages) {
            $next = $this->makeLink($currentPage + 1, $filename, 'next »');
        }
        //first and last are for opensearch
        $first = ['active'=> false, 'title' => 'first'];
        if ($currentPage > 1) {
            $first = $this->makeLink(1, $filename, 'first');
        }
        $last = ['active'=> false, 'title' => 'last'];
        if ($numPages > 1 && $currentPage < $numPages) {
            $last = $this->makeLink($numPages, $filename, 'last');
        }

        $this->links = [
            'prev'  => $prev,
            'next'  => $next,
            'first' => $first,
            'last'  => $last,
            'pages' => $pages,
        ];
    }

    protected function makeLink($pageNum, $filename, $title = null)
    {
        $title = $title === null ? $pageNum : $title;
        $url   = $filename . '&page=' . $pageNum;
        return [
            'active' => true,
            'url'    => $url,
            'title'  => $title,
            'html'   => '<a href="' . htmlspecialchars($url)
                . '" title="Page ' . $pageNum . '">'
                . htmlspecialchars($title)
                . '</a>',
        ];
    }

    public function getLinks()
    {
        return $this->links;
    }

    public function getFullUrls()
    {
        $arUrls  = array();
        foreach ($this->links as $key => $link) {
            if ($key == 'pages') {
                continue;
            }
            if ($link['active']) {
                $arUrls[$key] = str_replace(
                    '&amp;', '&',
                    Helper::fullUrl('/'  . $link['url'])
                );
            }
        }
        return $arUrls;
    }

    public function numPages()
    {
        return $this->numPages;
        return $this->pager->numPages();
    }
}

?>
