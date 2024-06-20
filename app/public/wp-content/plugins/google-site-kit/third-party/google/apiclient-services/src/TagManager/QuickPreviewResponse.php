<?php

/*
 * Copyright 2014 Google Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License"); you may not
 * use this file except in compliance with the License. You may obtain a copy of
 * the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS, WITHOUT
 * WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied. See the
 * License for the specific language governing permissions and limitations under
 * the License.
 */
namespace Google\Site_Kit_Dependencies\Google\Service\TagManager;

class QuickPreviewResponse extends \Google\Site_Kit_Dependencies\Google\Model
{
    /**
     * @var bool
     */
    public $compilerError;
    protected $containerVersionType = \Google\Site_Kit_Dependencies\Google\Service\TagManager\ContainerVersion::class;
    protected $containerVersionDataType = '';
    protected $syncStatusType = \Google\Site_Kit_Dependencies\Google\Service\TagManager\SyncStatus::class;
    protected $syncStatusDataType = '';
    /**
     * @param bool
     */
    public function setCompilerError($compilerError)
    {
        $this->compilerError = $compilerError;
    }
    /**
     * @return bool
     */
    public function getCompilerError()
    {
        return $this->compilerError;
    }
    /**
     * @param ContainerVersion
     */
    public function setContainerVersion(\Google\Site_Kit_Dependencies\Google\Service\TagManager\ContainerVersion $containerVersion)
    {
        $this->containerVersion = $containerVersion;
    }
    /**
     * @return ContainerVersion
     */
    public function getContainerVersion()
    {
        return $this->containerVersion;
    }
    /**
     * @param SyncStatus
     */
    public function setSyncStatus(\Google\Site_Kit_Dependencies\Google\Service\TagManager\SyncStatus $syncStatus)
    {
        $this->syncStatus = $syncStatus;
    }
    /**
     * @return SyncStatus
     */
    public function getSyncStatus()
    {
        return $this->syncStatus;
    }
}
// Adding a class alias for backwards compatibility with the previous class name.
\class_alias(\Google\Site_Kit_Dependencies\Google\Service\TagManager\QuickPreviewResponse::class, 'Google\\Site_Kit_Dependencies\\Google_Service_TagManager_QuickPreviewResponse');