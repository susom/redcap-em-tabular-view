<?php

namespace Stanford\ParentChild;

/** @var \Stanford\TabularView\TabularView $module */

try {
    $term = filter_var($_POST['term'], FILTER_SANITIZE_STRING);
    $module->setProjectId(filter_var($_POST['pid'], FILTER_SANITIZE_NUMBER_INT));
    $module->searchRecordViaMRN($term);

    //TODO build not repeating section
    ?>
    <table id="record-instances">
        <thead>
        <tr>
            <th>Date</th>
            <th>Instrument</th>
            <th>Summary</th>
            <th>URL</th>
        </tr>
        </thead>
        <tbody>
        <?php
        if ($module->getRecord()) {
            foreach ($module->getRecord() as $date => $instance) {
                ?>
                <tr>
                    <td><?php echo $instance['date'] ?></td>
                    <td><?php echo $instance['instrument'] ?></td>
                    <td><?php echo $instance['summary'] ?></td>
                    <td><a class="btn btn-primary" href="<?php echo $instance['url'] ?>">View Record</a></td>
                </tr>
                <?php
            }
        }
        ?>
        </tbody>
    </table>
    <?php
} catch (\LogicException $e) {
    echo "<div class='alert-danger'>" . $e->getMessage() . "</div>";
}