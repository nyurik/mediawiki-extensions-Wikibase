<?php

namespace Wikibase\Lib\Store\Sql\Terms;

use Wikibase\DataModel\Term\Fingerprint;
use Wikibase\Lib\StringNormalizer;
use Wikimedia\Rdbms\IDatabase;

/**
 * Trait for code reuse between DatabaseItemTermStoreWriter and DatabasePropertyTermStoreWriter
 *
 * @author Addshore
 * @see @ref md_docs_storage_terms
 * @license GPL-2.0-or-later
 */
trait FingerprintableEntityTermStoreTrait {

	private function termsArrayFromFingerprint( Fingerprint $fingerprint, StringNormalizer $stringNormalizer ): array {
		$termsArray = [];
		foreach ( $fingerprint->getLabels()->toTextArray() as $language => $label ) {
			$label = $stringNormalizer->cleanupToNFC( $label );
			$termsArray['label'][$language] = $label;
		}
		foreach ( $fingerprint->getDescriptions()->toTextArray() as $language => $description ) {
			$description = $stringNormalizer->cleanupToNFC( $description );
			$termsArray['description'][$language] = $description;
		}
		foreach ( $fingerprint->getAliasGroups()->toTextArray() as $language => $aliases ) {
			$aliases = array_map( [ $stringNormalizer, 'cleanupToNFC' ], $aliases );
			$termsArray['alias'][$language] = $aliases;
		}
		return $termsArray;
	}

	/**
	 * Of the given term in lang IDs, find those that aren’t used by any other items or properties.
	 *
	 * Currently, this does not account for term in lang IDs that may be used anywhere else,
	 * e.g. by other entity types; anyone who uses term in lang IDs elsewhere runs the risk
	 * of those terms being deleted at any time. This may be improved in the future.
	 *
	 * 1) Iterate through the IDs that we have been given and determine if they
	 * appear to be used or not in either the property or item term tables.
	 * 2) Select FOR UPDATE the rows in the wbt_property_terms and wbt_item_terms
	 * tables so they lock and nothing will happen to them.
	 *
	 * An alternative to this would be immediately lock all $termInLangIds, but that would
	 * lead to deadlocks. see T234948
	 *
	 * @param int[] $termInLangIds (wbtl_id)
	 * @return int[] wbtl_ids to be cleaned
	 */
	private function findActuallyUnusedTermInLangIds( array $termInLangIds, IDatabase $dbw ) {
		$unusedTermInLangIds = [];
		foreach ( $termInLangIds as $termInLangId ) {
			// Note: Not batching here is intentional to avoid deadlocks (see method comment)
			$usedInProperties = $dbw->selectField(
				'wbt_property_terms',
				'wbpt_term_in_lang_id',
				[ 'wbpt_term_in_lang_id' => $termInLangId ]
			);
			$usedInItems = $dbw->selectField(
				'wbt_item_terms',
				'wbit_term_in_lang_id',
				[ 'wbit_term_in_lang_id' => $termInLangId ]
			);

			if ( $usedInProperties === false && $usedInItems === false ) {
				$unusedTermInLangIds[] = $termInLangId;
			}
		}
		if ( $unusedTermInLangIds === [] ) {
			return [];
		}

		$termInLangIdsUsedInPropertiesSinceLastLoopRan = $dbw->selectFieldValues(
			'wbt_property_terms',
			'wbpt_term_in_lang_id',
			[ 'wbpt_term_in_lang_id' => $unusedTermInLangIds ],
			__METHOD__,
			[
				'FOR UPDATE'
			]
		);
		$termInLangIdsUsedInItemsSinceLastLoopRan = $dbw->selectFieldValues(
			'wbt_item_terms',
			'wbit_term_in_lang_id',
			[ 'wbit_term_in_lang_id' => $unusedTermInLangIds ],
			__METHOD__,
			[
				'FOR UPDATE'
			]
		);

		$finalUnusedTermInLangIds = array_diff(
			$unusedTermInLangIds,
			$termInLangIdsUsedInPropertiesSinceLastLoopRan,
			$termInLangIdsUsedInItemsSinceLastLoopRan
		);

		return $finalUnusedTermInLangIds;
	}

}
