<?php
/**
 * webtrees: online genealogy
 * Copyright (C) 2018 webtrees development team
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */
namespace Fisharebest\Webtrees\Module;

use Fisharebest\Webtrees\Database;
use Fisharebest\Webtrees\Family;
use Fisharebest\Webtrees\Filter;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\Query\QueryName;
use Fisharebest\Webtrees\Theme;
use Fisharebest\Webtrees\Tree;

/**
 * Class FamiliesSidebarModule
 */
class FamiliesSidebarModule extends AbstractModule implements ModuleSidebarInterface {
	/** {@inheritdoc} */
	public function getTitle() {
		return /* I18N: Name of a module/sidebar */ I18N::translate('Family list');
	}

	/** {@inheritdoc} */
	public function getDescription() {
		return /* I18N: Description of the “Families” module */ I18N::translate('A sidebar showing an alphabetic list of all the families in the family tree.');
	}

	/**
	 * This is a general purpose hook, allowing modules to respond to routes
	 * of the form module.php?mod=FOO&mod_action=BAR
	 *
	 * @param string $mod_action
	 */
	public function modAction($mod_action) {
		switch ($mod_action) {
			case 'ajax':
				header('Content-Type: text/html; charset=UTF-8');
				echo $this->getSidebarAjaxContent();
				break;
			default:
				http_response_code(404);
				break;
		}
	}

	/** {@inheritdoc} */
	public function defaultSidebarOrder() {
		return 50;
	}

	/** {@inheritdoc} */
	public function hasSidebarContent(Individual $individual) {
		return true;
	}

	/** {@inheritdoc} */
	public function getSidebarAjaxContent() {
		global $WT_TREE;

		$alpha   = Filter::get('alpha'); // All surnames beginning with this letter where "@"=unknown and ","=none
		$surname = Filter::get('surname'); // All indis with this surname.
		$search  = Filter::get('search');

		if ($search) {
			return $this->search($WT_TREE, $search);
		} elseif ($alpha == '@' || $alpha == ',' || $surname) {
			return $this->getSurnameFams($WT_TREE, $alpha, $surname);
		} elseif ($alpha) {
			return $this->getAlphaSurnames($WT_TREE, $alpha);
		} else {
			return '';
		}
	}

	/**
	 * Load this sidebar synchronously.
	 *
	 * @param Individual $individual
	 *
	 * @return string
	 */
	public function getSidebarContent(Individual $individual) {
		// Fetch a list of the initial letters of all surnames in the database
		$initials = QueryName::surnameAlpha($individual->getTree(), true, false, false);

		return view('sidebars/families', [
			'initials' => $initials,
		]);
	}

	/**
	 * Get the initial letters of surnames.
	 *
	 * @param Tree   $tree
	 * @param string $alpha
	 *
	 * @return string
	 */
	private function getAlphaSurnames(Tree $tree, $alpha) {
		$surnames = QueryName::surnames($tree, '', $alpha, true, true);
		$out      = '<ul>';
		foreach (array_keys($surnames) as $surname) {
			$out .= '<li class="sb_fam_surname_li"><a href="#" data-surname="' . e($surname) . '" data-alpha="' . e($alpha) . '" class="sb_fam_surname">' . e($surname) . '</a>';
			$out .= '<div class="name_tree_div"></div>';
			$out .= '</li>';
		}
		$out .= '</ul>';

		return $out;
	}

	/**
	 * Format a list of families.
	 *
	 * @param Tree   $tree
	 * @param string $alpha
	 * @param string $surname
	 *
	 * @return string
	 */
	public function getSurnameFams(Tree $tree, $alpha, $surname) {
		$families = QueryName::families($tree, $surname, $alpha, '', true);
		$out      = '<ul>';
		foreach ($families as $family) {
			if ($family->canShowName()) {
				$out .= '<li><a href="' . e($family->url()) . '">' . $family->getFullName() . ' ';
				if ($family->canShow()) {
					$marriage_year = $family->getMarriageYear();
					if ($marriage_year) {
						$out .= ' (' . $marriage_year . ')';
					}
				}
				$out .= '</a></li>';
			}
		}
		$out .= '</ul>';

		return $out;
	}

	/**
	 * Search for families in a tree.
	 *
	 * @param Tree   $tree  Search this tree
	 * @param string $query Search for this text
	 *
	 * @return string
	 */
	private function search(Tree $tree, $query) {
		if (strlen($query) < 2) {
			return '';
		}
		$rows = Database::prepare(
			"SELECT i_id AS xref" .
			" FROM `##individuals`, `##name`" .
			" WHERE (i_id LIKE CONCAT('%', :query_1, '%') OR n_sort LIKE CONCAT('%', :query_2, '%'))" .
			" AND i_id = n_id AND i_file = n_file AND i_file = :tree_id" .
			" ORDER BY n_sort COLLATE :collation" .
			" LIMIT 50"
		)->execute([
			'query_1'   => $query,
			'query_2'   => $query,
			'tree_id'   => $tree->getTreeId(),
			'collation' => I18N::collation(),
		])->fetchAll();

		$ids = [];
		foreach ($rows as $row) {
			$ids[] = $row->xref;
		}

		$vars = [];
		if (empty($ids)) {
			//-- no match : search for FAM id
			$where  = "f_id LIKE CONCAT('%', ?, '%')";
			$vars[] = $query;
		} else {
			//-- search for spouses
			$qs    = implode(',', array_fill(0, count($ids), '?'));
			$where = "(f_husb IN ($qs) OR f_wife IN ($qs))";
			$vars  = array_merge($vars, $ids, $ids);
		}

		$vars[] = $tree->getTreeId();
		$rows   = Database::prepare("SELECT f_id AS xref, f_file AS gedcom_id, f_gedcom AS gedcom FROM `##families` WHERE {$where} AND f_file=?")
		->execute($vars)
		->fetchAll();

		$out = '<ul>';
		foreach ($rows as $row) {
			$family = Family::getInstance($row->xref, $tree, $row->gedcom);
			if ($family->canShowName()) {
				$out .= '<li><a href="' . e($family->url()) . '">' . $family->getFullName() . ' ';
				if ($family->canShow()) {
					$marriage_year = $family->getMarriageYear();
					if ($marriage_year) {
						$out .= ' (' . $marriage_year . ')';
					}
				}
				$out .= '</a></li>';
			}
		}
		$out .= '</ul>';

		return $out;
	}
}
