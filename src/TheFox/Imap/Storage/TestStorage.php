<?php

namespace TheFox\Imap\Storage;

use Symfony\Component\Filesystem\Filesystem;

class TestStorage extends AbstractStorage
{
    public function getDirectorySeperator()
    {
        return '_';
    }

    public function setPath($path)
    {
        parent::setPath($path);

        if (!file_exists($this->getPath())) {
            $filesystem = new Filesystem();
            $filesystem->mkdir($this->getPath(), 0755);
        }
    }

    public function createFolder($folder)
    {

    }

    public function getFolders($baseFolder, $searchFolder, $recursive = false)
    {
        $folders = [];
        return $folders;
    }

    public function folderExists($folder)
    {
        return false;
    }

    public function getMailsCountByFolder($folder, $flags = null)
    {
        return 0;
    }

    public function addMail($mailStr, $folder, $flags, $recent)
    {
        return null;
    }

    public function removeMail($msgId)
    {

    }

    public function copyMailById($msgId, $folder)
    {

    }

    public function copyMailBySequenceNum($seqNum, $folder, $dstFolder)
    {

    }

    public function getPlainMailById($msgId)
    {
        return '';
    }

    public function getMsgSeqById($msgId)
    {
        return null;
    }

    public function getMsgIdBySeq($seqNum, $folder)
    {
        return null;
    }

    public function getMsgsByFlags($flags)
    {
        return [];
    }

    public function getFlagsById($msgId)
    {
        return [];
    }

    public function setFlagsById($msgId, $flags)
    {

    }

    public function getFlagsBySeq($seqNum, $folder)
    {
        return [];
    }

    public function setFlagsBySeq($seqNum, $folder, $flags)
    {

    }

    public function getNextMsgId()
    {
        return null;
    }
}
