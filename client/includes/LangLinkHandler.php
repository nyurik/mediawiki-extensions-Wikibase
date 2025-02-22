<?php

namespace Wikibase\Client;

use ParserOutput;
use Site;
use SiteLookup;
use Title;
use Wikibase\Client\Hooks\LanguageLinkBadgeDisplay;
use Wikibase\Client\Hooks\NoLangLinkHandler;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Services\Lookup\EntityLookup;
use Wikibase\DataModel\SiteLink;
use Wikibase\Lib\Store\SiteLinkLookup;

/**
 * @todo split this up and find a better home for stuff that adds
 * parser output properties and extension data.
 *
 * @license GPL-2.0-or-later
 * @author Nikola Smolenski <smolensk@eunet.rs>
 * @author Daniel Kinzler
 * @author Katie Filbert < aude.wiki@gmail.com >
 */
class LangLinkHandler {

	/**
	 * @var LanguageLinkBadgeDisplay
	 */
	private $badgeDisplay;

	/**
	 * @var NamespaceChecker
	 */
	private $namespaceChecker;

	/**
	 * @var SiteLinkLookup
	 */
	private $siteLinkLookup;

	/**
	 * @var EntityLookup
	 */
	private $entityLookup;

	/**
	 * @var SiteLookup
	 */
	private $siteLookup;

	/**
	 * @var string
	 */
	private $siteId;

	/**
	 * @var string
	 */
	private $siteGroup;

	/**
	 * @param LanguageLinkBadgeDisplay $badgeDisplay
	 * @param NamespaceChecker $namespaceChecker determines which namespaces wikibase is enabled on
	 * @param SiteLinkLookup $siteLinkLookup
	 * @param EntityLookup $entityLookup
	 * @param SiteLookup $siteLookup
	 * @param string $siteId The global site ID for the local wiki
	 * @param string $siteGroup The ID of the site group to use for showing language links.
	 */
	public function __construct(
		LanguageLinkBadgeDisplay $badgeDisplay,
		NamespaceChecker $namespaceChecker,
		SiteLinkLookup $siteLinkLookup,
		EntityLookup $entityLookup,
		SiteLookup $siteLookup,
		$siteId,
		$siteGroup
	) {
		$this->badgeDisplay = $badgeDisplay;
		$this->namespaceChecker = $namespaceChecker;
		$this->siteLinkLookup = $siteLinkLookup;
		$this->entityLookup = $entityLookup;
		$this->siteLookup = $siteLookup;
		$this->siteId = $siteId;
		$this->siteGroup = $siteGroup;
	}

	/**
	 * Finds the corresponding item on the repository and returns the item's site links.
	 *
	 * @param Title $title
	 *
	 * @return SiteLink[] A map of SiteLinks, indexed by global site id.
	 */
	public function getEntityLinks( Title $title ) {
		$links = [];

		$itemId = $this->siteLinkLookup->getItemIdForLink(
			$this->siteId,
			$title->getPrefixedText()
		);

		if ( $itemId !== null ) {
			//NOTE: SiteLinks we could get from $this->siteLinkLookup do not contain badges,
			//      so we have to fetch the links from the Item.

			/** @var Item $item */
			$item = $this->entityLookup->getEntity( $itemId );
			'@phan-var Item|null $item';

			if ( $item ) {
				$links = iterator_to_array( $item->getSiteLinkList() );
				$links = $this->indexLinksBySiteId( $links );
			} else {
				wfLogWarning( __METHOD__ . ": Could not load item " . $itemId->getSerialization()
					. " for " . $title->getPrefixedText() );
			}
		}

		return $links;
	}

	/**
	 * @param SiteLink[] $links
	 *
	 * @return SiteLink[] The SiteLinks in $links, indexed by site ID
	 */
	private function indexLinksBySiteId( array $links ) {
		$indexed = [];

		foreach ( $links as $link ) {
			$key = $link->getSiteId();
			$indexed[$key] = $link;
		}

		return $indexed;
	}

	/**
	 * @param SiteLink[] $links
	 *
	 * @return SiteLink[] The SiteLinks in $links, indexed by interwiki prefix.
	 */
	private function indexLinksByInterwiki( array $links ) {
		$indexed = [];

		foreach ( $links as $link ) {
			$siteId = $link->getSiteId();
			$site = $this->siteLookup->getSite( $siteId );

			if ( !$site ) {
				continue;
			}

			$navIds = $site->getNavigationIds();
			$key = reset( $navIds );

			if ( $key !== false ) {
				$indexed[$key] = $link;
			}
		}

		return $indexed;
	}

	/**
	 * Checks if a page have interwiki links from Wikidata repo?
	 * Disabled for a page when either:
	 * - Wikidata not enabled for namespace
	 * - nel parser function = * (suppress all repo links)
	 *
	 * @param Title $title
	 * @param ParserOutput $out
	 *
	 * @return bool
	 */
	public function useRepoLinks( Title $title, ParserOutput $out ) {
		// use repoLinks in only the namespaces specified in settings
		if ( $this->namespaceChecker->isWikibaseEnabled( $title->getNamespace() ) === true ) {
			$nel = $this->getNoExternalLangLinks( $out );

			if ( in_array( '*', $nel ) ) {
				return false;
			}

			return true;
		}

		return false;
	}

	/**
	 * Returns a filtered version of $repoLinks, containing only links that should be considered
	 * for combining with the local inter-language links. This takes into account the
	 * {{#noexternallanglinks}} parser function, and also removed any link to
	 * this wiki itself.
	 *
	 * This function does not remove links to wikis for which there is already an
	 * inter-language link defined in the local wikitext. This is done later
	 * by getEffectiveRepoLinks().
	 *
	 * @param ParserOutput $out
	 * @param array $repoLinks An array that uses global site IDs as keys.
	 *
	 * @return SiteLink[] A filtered copy of $repoLinks, with any inappropriate
	 *         entries removed.
	 */
	public function suppressRepoLinks( ParserOutput $out, array $repoLinks ) {
		$nel = $this->getNoExternalLangLinks( $out );

		foreach ( $nel as $code ) {
			if ( $code === '*' ) {
				// all are suppressed
				return [];
			}

			$sites = $this->siteLookup->getSites();
			if ( $sites->hasNavigationId( $code ) ) {
				$site = $sites->getSiteByNavigationId( $code );
				$wiki = $site->getGlobalId();
				unset( $repoLinks[$wiki] );
			}
		}

		unset( $repoLinks[$this->siteId] ); // remove self-link

		return $repoLinks;
	}

	/**
	 * Filters the given list of links by site group:
	 * Any links pointing to a site that is not in $allowedGroups will be removed.
	 *
	 * @param array $repoLinks An array that uses global site IDs as keys.
	 * @param string[] $allowedGroups A list of allowed site groups
	 *
	 * @return array A filtered copy of $repoLinks, retaining only the links
	 *         pointing to a site in an allowed group.
	 */
	public function filterRepoLinksByGroup( array $repoLinks, array $allowedGroups ) {
		foreach ( $repoLinks as $wiki => $link ) {
			if ( !$this->siteLookup->getSite( $wiki ) ) {
				unset( $repoLinks[$wiki] );
				continue;
			}

			$site = $this->siteLookup->getSite( $wiki );

			if ( !in_array( $site->getGroup(), $allowedGroups ) ) {
				unset( $repoLinks[$wiki] );
				continue;
			}
		}

		return $repoLinks;
	}

	/**
	 * Get the noexternallanglinks page property from the ParserOutput,
	 * which is set by the {{#noexternallanglinks}} parser function.
	 *
	 * @see NoLangLinkHandler::getNoExternalLangLinks
	 *
	 * @param ParserOutput $out
	 *
	 * @return string[] A list of language codes, identifying which repository links to ignore.
	 *         Empty if {{#noexternallanglinks}} was not used on the page.
	 */
	public function getNoExternalLangLinks( ParserOutput $out ) {
		return NoLangLinkHandler::getNoExternalLangLinks( $out );
	}

	/**
	 * Converts a list of interwiki links into an associative array that maps
	 * global site IDs to the respective target pages on the designated wikis.
	 *
	 * @param string[] $flatLinks
	 *
	 * @return string[] An associative array, using site IDs for keys
	 *           and the target pages on the respective wiki as the associated value.
	 */
	private function localLinksToArray( array $flatLinks ) {
		$links = [];
		$sites = $this->siteLookup->getSites();

		foreach ( $flatLinks as $s ) {
			$parts = explode( ':', $s, 2 );
			if ( count( $parts ) !== 2 ) {
				continue;
			}

			list( $lang, $page ) = $parts;

			if ( $sites->hasNavigationId( $lang ) ) {
				$site = $sites->getSiteByNavigationId( $lang );
				$wiki = $site->getGlobalId();
				$links[$wiki] = $page;
			} else {
				wfWarn( "Failed to map interlanguage prefix $lang to a global site ID." );
			}
		}

		return $links;
	}

	/**
	 * Look up sitelinks for the given title on the repository and filter them
	 * taking into account any applicable configuration and any use of the
	 * {{#noexternallanglinks}} function on the page.
	 *
	 * The result is an associative array of links that should be added to the
	 * current page, excluding any target sites for which there already is a
	 * link on the page.
	 *
	 * @param Title $title The page's title
	 * @param ParserOutput $out   Parsed representation of the page
	 *
	 * @return SiteLink[] An associative array, using site IDs for keys
	 *         and the target pages in the respective languages as the associated value.
	 */
	public function getEffectiveRepoLinks( Title $title, ParserOutput $out ) {
		if ( !$this->useRepoLinks( $title, $out ) ) {
			return [];
		}

		$allowedGroups = [ $this->siteGroup ];

		$onPageLinks = $out->getLanguageLinks();
		$onPageLinks = $this->localLinksToArray( $onPageLinks );

		$repoLinks = $this->getEntityLinks( $title );

		$repoLinks = $this->filterRepoLinksByGroup( $repoLinks, $allowedGroups );
		$repoLinks = $this->suppressRepoLinks( $out, $repoLinks );

		$repoLinks = array_diff_key( $repoLinks, $onPageLinks ); // remove local links

		return $repoLinks;
	}

	/**
	 * Look up sitelinks for the given title on the repository and add them
	 * to the ParserOutput object, taking into account any applicable
	 * configuration and any use of the {{#noexternallanglinks}} function on the page.
	 *
	 * The language links are not sorted, call sortLanguageLinks() to do that.
	 *
	 * @param Title $title The page's title
	 * @param ParserOutput $out Parsed representation of the page
	 */
	public function addLinksFromRepository( Title $title, ParserOutput $out ) {
		$repoLinks = $this->getEffectiveRepoLinks( $title, $out );

		$this->addLinksToOutput( $repoLinks, $out );

		$repoLinksByInterwiki = $this->indexLinksByInterwiki( $repoLinks );
		$this->badgeDisplay->attachBadgesToOutput( $repoLinksByInterwiki, $out );
	}

	/**
	 * Adds the given SiteLinks to the given ParserOutput.
	 *
	 * @param SiteLink[] $links
	 * @param ParserOutput $out
	 */
	private function addLinksToOutput( array $links, ParserOutput $out ) {
		foreach ( $links as $siteId => $siteLink ) {
			$page = $siteLink->getPageName();
			$targetSite = $this->siteLookup->getSite( $siteId );

			if ( !$targetSite ) {
				wfLogWarning( "Unknown wiki '$siteId' used as sitelink target" );
				continue;
			}

			$interwikiCode = $this->getInterwikiCodeFromSite( $targetSite );

			if ( $interwikiCode ) {
				$link = "$interwikiCode:$page";
				$out->addLanguageLink( $link );
			} else {
				wfWarn( "No interlanguage prefix found for $siteId." );
			}
		}
	}

	/**
	 * Extracts the local interwiki code, which in case of the
	 * wikimedia site groups, is always the global id's prefix.
	 *
	 * @fixme put somewhere more sane and use site identifiers data,
	 * so that this works in non-wikimedia cases where the assumption
	 * is not true.
	 *
	 * @param Site $site
	 *
	 * @return string
	 */
	public function getInterwikiCodeFromSite( Site $site ) {
		// FIXME: We should use $site->getInterwikiIds, but the interwiki ids in
		// the sites table are wrong currently, see T137537.
		$id = $site->getGlobalId();
		$id = preg_replace( '/(wiki\w*|wiktionary)$/', '', $id );
		$id = strtr( $id, [ '_' => '-' ] );
		if ( !$id ) {
			$id = $site->getLanguageCode();
		}
		return $id;
	}

}
