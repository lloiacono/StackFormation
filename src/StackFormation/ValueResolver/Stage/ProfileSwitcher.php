<?php

namespace StackFormation\ValueResolver\Stage;

class ProfileSwitcher extends AbstractValueResolverStage
{

    public function invoke($string)
    {
        $string = preg_replace_callback(
            '/\[profile:([^:\]\[]+?):([^\]\[]+?)\]/',
            function ($matches) {
                // recursively create another ValueResolver, but this time with a different profile
                $subValueResolver = new \StackFormation\ValueResolver\ValueResolver(
                    $this->valueResolver->getDependencyTracker(),
                    $this->valueResolver->getProfileManager(),
                    $this->valueResolver->getConfig(),
                    $matches[1]
                );
                return $subValueResolver->resolvePlaceholders($matches[2], $this->sourceBlueprint, $this->sourceType, $this->sourceKey);
            },
            $string
        );
        return $string;
    }

}
