<?php

namespace ExternalModuleExercises\HelloWorldModule;

use ExternalModules\AbstractExternalModule;

class HelloWorldModule extends AbstractExternalModule {

    // This is generally where your module's hooks will live
    function redcap_every_page_top($project_id) {
        print_r('Hello world! I am a message produced by a hook!');
    }

}
