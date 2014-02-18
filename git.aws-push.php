<?php

/*
 * (c) Michal Kvasničák <http://kvasnicak.info/>
 *
 * For the full copyright and license information, please view the README.md
 * file that was distributed with this source code.
 */

set_exception_handler(
    function(Exception $e) {
        echo $e->getMessage() . "\n";
        exit(1);
    }
);


/**
 * Class ElasticBeanstalkGitDeployer
 *
 * Command line tool for application deployment to Amazon Elastic Beanstalk using GIT
 *
 * @author Amazon.com, Inc.
 * @author Michal Kvasničák
 */
class ElasticBeanstalkGitDeployer
{

    /**
     * Hash of region => endpoint git repository mapping
     *
     * @var array
     */
    private static $regionsToEndpoint = [
        'eu-west-1' => 'git.elasticbeanstalk.eu-west-1.amazonaws.com',
        'us-east-1' => 'git.elasticbeanstalk.us-east-1.amazonaws.com',
        'us-west-1' => 'git.elasticbeanstalk.us-west-1.amazonaws.com',
        'us-west-2' => 'git.elasticbeanstalk.us-west-2.amazonaws.com',
        'ap-northeast-1' => 'git.elasticbeanstalk.ap-northeast-1.amazonaws.com',
        'ap-southeast-1' => 'git.elasticbeanstalk.ap-southeast-1.amazonaws.com',
        'ap-southeast-2' => 'git.elasticbeanstalk.ap-southeast-2.amazonaws.com',
        'sa-east-1' => 'git.elasticbeanstalk.sa-east-1.amazonaws.com'
    ];


    /**
     * Amazon AWS Access Key
     *
     * @var string
     */
    protected $accessKey;


    /**
     * Amazon AWS Secret Key
     *
     * @var string
     */
    protected $secretKey;


    /**
     * Creates Amazon Elastic Beanstalk Deployer
     *
     * @param string $accessKey
     * @param string $secretKey
     */
    public function __construct($accessKey, $secretKey)
    {
        foreach ([$accessKey, $secretKey] as $key) {
            $this->validateString($key);
            $this->validateNotEmpty($key);
        }

        $this->accessKey = $accessKey;
        $this->secretKey = $secretKey;
    }


    /**
     * Deploys application using git to given environment and region
     *
     * @param string $application amazon elastic beanstalk application name
     * @param string $environment amazon elastic beanstalk application environment name
     * @param string $region amazon aws region e.g. eu-west-1
     * @param null|string $commitId     git commit id in current branch to push to amazon eb
     *
     * @throws RuntimeException
     */
    public function deploy($application, $environment, $region, $commitId = null)
    {
        $endpoint = $this->regionToEndpoint($region);
        $timestamp = time();

        $headers = [
            gmdate('Ymd', $timestamp),        // date in format Ymd
            $region,                    // amazon eb region
            "devtools",
            "aws4_request"
        ];

        $commit = $this->getCommitId($commitId);

        $repositoryPath = $this->generateRepositoryPath($application, $commit, $environment);
        $requestSignature = $this->generateRequestSignature($endpoint, $repositoryPath);
        $stringToSign = $this->generateStringToSign($timestamp, $headers, $requestSignature);
        $password = $this->generatePassword($timestamp, $headers, $stringToSign, $this->secretKey);
        $remote = $this->generateGitRepositoryUrl($this->accessKey, $password, $endpoint, $repositoryPath);

        echo "Pushing application '$application' to environment '$environment'\n";

        exec("git push -f $remote HEAD:refs/heads/master", $output, $return);

        if ($return != 0) {
            throw new RuntimeException("Error in pushing to Amazon Elastic Beanstalk");
        }

        echo join("\n", $output) . "\n"; // write out output from command execution

        exit(0);
    }


    /**
     * @param string $accessKey         amazon aws access key
     * @param string $password          request password
     * @param string $endpoint          amazon aws eb endpoint url
     * @param string $repositoryPath    endpoint git repository path
     *
     * @return string
     */
    private function generateGitRepositoryUrl($accessKey, $password, $endpoint, $repositoryPath)
    {
        return "https://$accessKey:$password@{$endpoint}$repositoryPath";
    }


    /**
     * Generates string to sign
     *
     * @param int $timestamp
     * @param array|string $scope           request headers [date, region, "devtools", "aws4_request"]
     * @param string $requestSignature      request signature from generateRequestSignature()
     *
     * @return string
     */
    private function generateStringToSign($timestamp, $scope, $requestSignature)
    {
        $date = gmdate('Ymd\THis', $timestamp);

        $scope = is_array($scope) ? join('/', $scope) : $scope; // normalize headers to scope

        return "AWS4-HMAC-SHA256\n$date\n$scope\n$requestSignature";
    }


    /**
     * Generates password signature for request
     *
     * @param int $timestamp
     * @param array $headers
     * @param string $stringToSign
     * @param string $secretKey
     *
     * @return string
     */
    private function generatePassword($timestamp, array $headers, $stringToSign, $secretKey)
    {
        $datetime = gmdate('Ymd\THis\Z', $timestamp);

        $header = "AWS4{$secretKey}";

        foreach ($headers as $key) {
            $header = hash_hmac('sha256', $key, $header, true);
        }

        $pass = hash_hmac('sha256', $stringToSign, $header);

        return "{$datetime}{$pass}";
    }


    /**
     * Generates git repository path
     *
     * @param string $application
     * @param string $commitId
     * @param null|string $environment
     *
     * @return string
     */
    private function generateRepositoryPath($application, $commitId, $environment = null)
    {
        $path = sprintf(
            "/v1/repos/%s/commitid/%s",
            bin2hex($application),
            bin2hex($commitId)
        );

        if ($environment) {
            $path .= sprintf("/environment/%s", bin2hex($environment));
        }

        return $path;
    }


    /**
     * Gets endpoint url for given amazon aws region
     *
     * @param string $region
     *
     * @return string
     * @throws InvalidArgumentException
     */
    private function regionToEndpoint($region)
    {
        if (!in_array($region, array_keys(self::$regionsToEndpoint), TRUE)) {
            throw new InvalidArgumentException("Unknown region $region.");
        }

        return self::$regionsToEndpoint[$region];
    }


    /**
     * Generates request signature
     *
     * @param string $endpoint      amazon elastic beanstalk git end point url
     * @param string $path      path to git repository on host
     *
     * @return string
     */
    private function generateRequestSignature($endpoint, $path)
    {
        return hash('sha256', "GIT\n$path\n\nhost:$endpoint\n\nhost\n");
    }



    /**
     * Validates if key is string
     *
     * @param string $key
     *
     * @throws InvalidArgumentException
     */
    private function validateString($key)
    {
        if (!is_string($key)) {
            throw new InvalidArgumentException('Keys has to be strings.');
        }
    }


    /**
     * Validates if key is not empty
     *
     * @param string $key
     *
     * @throws InvalidArgumentException
     */
    private function validateNotEmpty($key)
    {
        if (empty($key)) {
            throw new InvalidArgumentException('Keys has to be set.');
        }
    }


    /**
     * Gets type of git object
     *
     * @param string $commit
     *
     * @return string
     */
    private function typeOfGitObject($commit)
    {
        return trim(exec("git cat-file -t $commit"));
    }


    /**
     * Checks if commit is valid in current branch or gets last commit id
     *
     * @param null|string $commit       git commit hash or null if we want last git commit in current branch
     *
     * @return string
     * @throws Exception
     */
    private function getCommitId($commit = null)
    {
        $commit = $commit ? : 'HEAD';

        $id = trim(exec("git rev-parse $commit"));

        $type = $this->typeOfGitObject($commit);

        if ($type == "commit") {
            return $id;
        } else {
            throw new Exception("Invalid commit: $commit is of type $type.");
        }
    }

}

// load arguments
$options = getopt('i:s:a:e:r:ch');

if (count($options) == 0 || array_key_exists('h', $options)) {
    echo "Amazon Elastic Beanstalk Deployer\n";
    echo "---------------------------------\n";
    echo "Usage:\n";
    echo "php git.aws-push.php -i access_key -s secret_key -a application_name -e environment_name -r region [-c
    commit_id]\n\n";
    echo "Arguments:\n";
    echo "-i [required] Amazon AWS Access Key\n";
    echo "-s [required] Amazon AWS Secret Key\n";
    echo "-a [required] Amazon Elastic Beanstalk application name\n";
    echo "-e [required] Amazon Elastic Beanstalk application environment name\n";
    echo "-r [required] Amazon AWS region e.g. eu-west-1\n";
    echo "-c [optional] Git commit id in current branch, if omitted last commit id will be used\n";
    echo "\n";
} else {
    foreach (str_split('isaer', 1) as $argument) {
        if (!isset($options[$argument])) {
            throw new RuntimeException("Missing argument -$argument.");
        }
    }

    $deployer = new ElasticBeanstalkGitDeployer($options['i'], $options['s']);
    $deployer->deploy($options['a'], $options['e'], $options['r'], isset($options['c']) ? $options['c'] : null);
}