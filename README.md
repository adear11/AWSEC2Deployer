# Amazon Web Services EC2 PHP Deployment #

This is a simple script that I wrote to handle deploying a PHP application on multiple load balanced EC2 instances. I thought
someone else might find it useful, so I decided to put it on GitHub.

This is currently based on version 1.5.15 of the AWS PHP SDK. Once version 2 of the Amazon SDK is fleshed out, I plan to
update this to use the new SDK.

## Usage ##

In order to use this script, you must have the AWS SDK available and configured with your AWS credentials. There are
ample resources available for how to get this setup, so I'm not going to tell you how to do it.

You will also need to have a user setup on each of the target servers that can authenticate via a SSH key.

Other than that, you just need to fill in the values that pertain to your project/application and you should be good to go.

### Required Values ###
<pre>
    $loadBalancer = "The name of your ELB";
    $docRoot      = "The absolute path to the root of your project";
    $deploymentUser = "The user mentioned above that can authenticate with an SSH key";
</pre>

## Misc. ##

This script assumes you are using Composer to manage your dependencies. However if you aren't using composer, or for that
matter if you aren't using Git, simply modify the $deployCommand string.

It attempts to be semi-intelligent about how it deploys the app. After it gets the list of instances behind the LB, it
will first de-register the target instance so that it doesn't receive any requests while the update is going on. After
 the de-registration happens, the code is updated and the instance is put back in service. The script checks every second
 to see that the target instance is in service. Once it is, it will go on to the next server in the list.