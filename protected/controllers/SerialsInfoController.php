public function actionSerialReport()
{
    $model=new SerialsInfo('search');
    $model->unsetAttributes();  // clear any default values
    if(isset($_GET['SerialsInfo']))
        $model->attributes=$_GET['SerialsInfo'];

    $this->render('serialReport', array(
        'model'=>$model,
    ));
}