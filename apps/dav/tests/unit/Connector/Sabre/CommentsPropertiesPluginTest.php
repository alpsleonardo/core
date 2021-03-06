<?php
/**
 * @author Arthur Schiwon <blizzz@owncloud.com>
 *
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OCA\DAV\Tests\unit\Connector\Sabre;

use \OCA\DAV\Connector\Sabre\CommentPropertiesPlugin as CommentPropertiesPluginImplementation;

class CommentsPropertiesPluginTest extends \Test\TestCase {

	/** @var  CommentPropertiesPluginImplementation */
	protected $plugin;
	protected $commentsManager;
	protected $userSession;
	protected $server;

	public function setUp() {
		parent::setUp();

		$this->commentsManager = $this->getMock('\OCP\Comments\ICommentsManager');
		$this->userSession = $this->getMock('\OCP\IUserSession');

		$this->server = $this->getMockBuilder('\Sabre\DAV\Server')
			->disableOriginalConstructor()
			->getMock();

		$this->plugin = new CommentPropertiesPluginImplementation($this->commentsManager, $this->userSession);
		$this->plugin->initialize($this->server);
	}

	public function nodeProvider() {
		$mocks = [];
		foreach(['\OCA\DAV\Connector\Sabre\File', '\OCA\DAV\Connector\Sabre\Directory', '\Sabre\DAV\INode'] as $class) {
			$mocks[] = 	$this->getMockBuilder($class)
				->disableOriginalConstructor()
				->getMock();
		}

		return [
			[$mocks[0], true],
			[$mocks[1], true],
			[$mocks[2], false]
		];
	}

	/**
	 * @dataProvider nodeProvider
	 * @param $node
	 * @param $expectedSuccessful
	 */
	public function testHandleGetProperties($node, $expectedSuccessful) {
		$propFind = $this->getMockBuilder('\Sabre\DAV\PropFind')
			->disableOriginalConstructor()
			->getMock();

		if($expectedSuccessful) {
			$propFind->expects($this->exactly(3))
				->method('handle');
		} else {
			$propFind->expects($this->never())
				->method('handle');
		}

		$this->plugin->handleGetProperties($propFind, $node);
	}

	public function baseUriProvider() {
		return [
			['owncloud/remote.php/webdav/', '4567', 'owncloud/remote.php/dav/comments/files/4567'],
			['owncloud/remote.php/files/', '4567', 'owncloud/remote.php/dav/comments/files/4567'],
			['owncloud/wicked.php/files/', '4567', null]
		];
	}

	/**
	 * @dataProvider baseUriProvider
	 * @param $baseUri
	 * @param $fid
	 * @param $expectedHref
	 */
	public function testGetCommentsLink($baseUri, $fid, $expectedHref) {
		$node = $this->getMockBuilder('\OCA\DAV\Connector\Sabre\File')
			->disableOriginalConstructor()
			->getMock();
		$node->expects($this->any())
			->method('getId')
			->will($this->returnValue($fid));

		$this->server->expects($this->once())
			->method('getBaseUri')
			->will($this->returnValue($baseUri));

		$href = $this->plugin->getCommentsLink($node);
		$this->assertSame($expectedHref, $href);
	}

	public function userProvider() {
		return [
			[$this->getMock('\OCP\IUser')],
			[null]
		];
	}

	/**
	 * @dataProvider userProvider
	 * @param $user
	 */
	public function testGetUnreadCount($user) {
		$node = $this->getMockBuilder('\OCA\DAV\Connector\Sabre\File')
			->disableOriginalConstructor()
			->getMock();
		$node->expects($this->any())
			->method('getId')
			->will($this->returnValue('4567'));

		$this->userSession->expects($this->once())
			->method('getUser')
			->will($this->returnValue($user));

		$this->commentsManager->expects($this->any())
			->method('getNumberOfCommentsForObject')
			->will($this->returnValue(42));

		$unread = $this->plugin->getUnreadCount($node);
		if(is_null($user)) {
			$this->assertNull($unread);
		} else {
			$this->assertSame($unread, 42);
		}
	}

}
