<?php

/**
 * @package blog
 */

/**
 * Blog holder to display summarised blog entries.
 *
 * A blog holder is the leaf end of a BlogTree, but can also be used standalone in simpler circumstances.
 * BlogHolders can only hold BlogEntries, BlogTrees can only hold BlogTrees and BlogHolders
 * BlogHolders have a form on them for easy posting, and an owner that can post to them, BlogTrees don't
 */
class BlogHolder extends BlogTree implements PermissionProvider {
	static $icon = "blog/images/blogholder";

	static $db = array(
		'TrackBacksEnabled' => 'Boolean',
		'AllowCustomAuthors' => 'Boolean',
	);

	static $has_one = array(
		'Owner' => 'Member',
	);

	static $allowed_children = array(
		'BlogEntry'
	);

	function getCMSFields() {
		$blogOwners = $this->blogOwners(); 

		SiteTree::disableCMSFieldsExtensions();
		$fields = parent::getCMSFields();
		SiteTree::enableCMSFieldsExtensions();

		$fields->addFieldToTab('Root.Content.Main', new CheckboxField('TrackBacksEnabled', 'Enable TrackBacks'));
		$fields->addFieldToTab('Root.Content.Main', new DropdownField('OwnerID', 'Blog owner', $blogOwners->toDropDownMap('ID', 'Name', 'None')));
		$fields->addFieldToTab('Root.Content.Main', new CheckboxField('AllowCustomAuthors', 'Allow non-admins to have a custom author field'));

		$this->extend('updateCMSFields', $fields);

		return $fields;
	}
	
	/**
	 * Get members who have BLOGMANAGEMENT and ADMIN permission
	 */ 
	function blogOwners($sort = 'Name', $direction = "ASC") {
		$adminMembers = Permission::get_members_by_permission('ADMIN'); 
		$blogOwners = Permission::get_members_by_permission('BLOGMANAGEMENT');
		
		if(!$adminMembers) $adminMembers = new DataObjectSet(); 
		if(!$blogOwners) $blogOwners = new DataObjectSet();
		
		$blogOwners->merge($adminMembers);
		$blogOwners->sort($sort, $direction);
		
		$this->extend('extendBlogOwners', $blogOwners);
		
		return $blogOwners;
	}

	public function BlogHolderIDs() {
		return array( $this->ID );
	}

	/*
	 * @todo: These next few functions don't really belong in the model. Can we remove them?
	 */

	/**
	 * Only display the blog entries that have the specified tag
	 */
	function ShowTag() {
		if($this->request->latestParam('Action') == 'tag') {
			return Convert::raw2xml(Director::urlParam('ID'));
		}
	}

	/**
	 * Check if url has "/post"
	 */
	function isPost() {
		return $this->request->latestParam('Action') == 'post';
	}

	/**
	 * Link for creating a new blog entry
	 */
	function postURL(){
		return $this->Link('post');
	}

	/**
	 * Returns true if the current user is an admin, or is the owner of this blog
	 *
	 * @return Boolean
	 */
	function IsOwner() {
		return (Permission::check('BLOGMANAGEMENT') || Permission::check('ADMIN'));
	}

	/**
	 * Create default blog setup
	 */
	function requireDefaultRecords() {
		parent::requireDefaultRecords();

		$blogHolder = DataObject::get_one('BlogHolder');
		//TODO: This does not check for whether this blogholder is an orphan or not
		if(!$blogHolder) {
			$blogholder = new BlogHolder();
			$blogholder->Title = "Blog";
			$blogholder->URLSegment = "blog";
			$blogholder->Status = "Published";

			$widgetarea = new WidgetArea();
			$widgetarea->write();

			$blogholder->SideBarID = $widgetarea->ID;
			$blogholder->write();
			$blogholder->publish("Stage", "Live");

			$managementwidget = new BlogManagementWidget();
			$managementwidget->ParentID = $widgetarea->ID;
			$managementwidget->write();

			$tagcloudwidget = new TagCloudWidget();
			$tagcloudwidget->ParentID = $widgetarea->ID;
			$tagcloudwidget->write();

			$archivewidget = new ArchiveWidget();
			$archivewidget->ParentID = $widgetarea->ID;
			$archivewidget->write();

			$widgetarea->write();

			$blog = new BlogEntry();
			$blog->Title = _t('BlogHolder.SUCTITLE', "SilverStripe blog module successfully installed");
			$blog->URLSegment = 'sample-blog-entry';
			$blog->Tags = _t('BlogHolder.SUCTAGS',"silverstripe, blog");
			$blog->Content = _t('BlogHolder.SUCCONTENT',"<p>Congratulations, the SilverStripe blog module has been successfully installed. This blog entry can be safely deleted. You can configure aspects of your blog (such as the widgets displayed in the sidebar) in <a href=\"admin\">the CMS</a>.</p>");
			$blog->Status = "Published";
			$blog->ParentID = $blogholder->ID;
			$blog->write();
			$blog->publish("Stage", "Live");

			DB::alteration_message("Blog page created","created");
		}
	}
}

class BlogHolder_Controller extends BlogTree_Controller {
	static $allowed_actions = array(
		'index',
		'tag',
		'date',
		'metaweblog',
		'postblog' => 'BLOGMANAGEMENT',
		'post' => 'BLOGMANAGEMENT',
		'BlogEntryForm' => 'BLOGMANAGEMENT',
	);
	
	function init() {
		parent::init();
		Requirements::themedCSS("bbcodehelp");
	}

	/**
	 * Return list of usable tags for help
	 */
	function BBTags() {
		return BBCodeParser::usable_tags();
	}

	
}


?>
