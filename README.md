# Amazon Elastic Beanstalk Deployer

Deploy to Amazon Elastic Beanstalk without need to install Elastic Beanstalk command line tool.
Really helpful on Continuous Integration servers.

## Requirements:
* Git installed and configured
* PHP >= 5.4

## Usage:
1. Create application and environment/environments on Amazon Elastic Beanstalk.
2. From application root run `php git.aws-push.php -i access_key -s secret_key -a application_name -e environment_name -r region [-c
    commit_id]`

### Arguments:
* -i *[required]* Amazon AWS Access Key
* -s *[required]* Amazon AWS Secret Key
* -a *[required]* Amazon Elastic Beanstalk application name
* -e *[required]* Amazon Elastic Beanstalk application environment name
* -r *[required]* Amazon AWS region e.g. eu-west-1
* -c *[optional]* Git commit id in current branch, if omitted last commit id will be used

## License:
Copyright (c) Michal Kvasničák <http://kvasnicak.info/>

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is furnished
to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
THE SOFTWARE.