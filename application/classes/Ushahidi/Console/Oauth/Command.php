<?php defined('SYSPATH') or die('No direct script access');

/**
 * Ushahidi Console OAuth Command
 *
 * Extra getters for client and scopes.
 *
 * @author     Ushahidi Team <team@ushahidi.com>
 * @package    Ushahidi\Console
 * @copyright  2014 Ushahidi
 * @license    https://www.gnu.org/licenses/agpl-3.0.html GNU Affero General Public License Version 3 (AGPL3)
 */

use Ushahidi\Console\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputInterface;

abstract class Ushahidi_Console_Oauth_Command extends Command {

	protected $server;

	public function __construct()
	{
		// Load the OAuth2 server into all OAuth commands.
		$this->server = new Koauth_OAuth2_Server();

		parent::__construct();
	}

	protected function supported_scopes()
	{
		// Load supported scopes from config.
		return Kohana::$config->load('koauth.supported_scopes');
	}

	protected function get_client(InputInterface $input, OutputInterface $output = NULL)
	{
		$client = $input->getOption('client');

		if (!$client AND $output)
		{
			// If no client was given, and `$output` is passed, we can ask for
			// the user interactively and validate it against the known clients.
			$clients = Arr::pluck(Ushahidi_Console_OAuth_Client::db_list(), 'client_id');
			$ask = function($client) use ($clients)
			{
				if (!in_array($client, $clients))
					throw new RuntimeException('Unknown client "' . $client . '", valid options are: ' . implode(', ', $clients));

				return $client;
			};

			$client = $this->getHelperSet()->get('dialog')
				->askAndValidate($output, 'For which client? ', $ask, FALSE, NULL, $clients)
				;
		}

		return $client;
	}

	protected function get_scopes(InputInterface $input)
	{
		$scopes = $input->getOption('scope'); // yes, singular!
		if ($scopes)
		{
			if (count($scopes) === 1)
			{
				// If only one scope was passed, it might be a "quoted scope list",
				// so we enforce the conversion to array.
				$scopes = explode(' ', current($scopes));
			}

			$invalid = array_diff($scopes, $this->supported_scopes());
			if ($invalid)
				throw new RuntimeException(
					'Invalid scopes given: ' . implode(', ', $invalid) . PHP_EOL .
					'Supported scopes are: ' . implode(', ', $supported));
		}

		return $scopes;
	}

	protected function get_user(InputInterface $input, OutputInterface $output = NULL)
	{
		// Username to user ID converter.
		// TODO: use the repo!
		$userid = function($username)
		{
			if (!$username)
				return NULL;

			return DB::select('id')
				->from('users')
				->where('username', '=', $username)
				->execute()
					->get('id')
					;
		};

		$username = $input->getOption('user');
		$user = NULL;

		if ($username)
		{
			// Check that the given username exists.
			$user = $userid($username);
			if (!$user)
				throw new RuntimeException('Unknown user "' . $username . '"');
		}
		elseif ($output)
		{
			// If required, `$output` will be passed and we can interactively
			// request the user to provide a username.
			$ask = function($username) use ($userid)
			{
				// And check that the given username exists.
				$user = $userid($username);
				if (!$user)
					throw new RuntimeException('Unknown user "' . $user . '", please try again');

				return $user;
			};

			$user = $this->getHelperSet()->get('dialog')
				->askAndValidate($output, 'For which user? ', $ask)
				;
		}

		return $user;
	}
}
