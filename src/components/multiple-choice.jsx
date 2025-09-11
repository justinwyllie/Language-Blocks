import Form from 'react-bootstrap/Form';
import useState from 'react';
import Row from 'react-bootstrap/Row';
import Col from 'react-bootstrap/Col';
import Alert from 'react-bootstrap/Alert';
import { useSelect } from '@wordpress/data';
import { useRef } from '@wordpress/element';
import { useEntityProp } from '@wordpress/core-data';
import { Formik, FieldArray , useFormikContext } from 'formik';
import { Instruction, MultipleChoiceQuestion, LinkPanel, AuthorPanel} from './components';
import { LABELS } from '../translations';
import { useBlockProps, RichText } from '@wordpress/block-editor'; 
import _ from 'underscore';

const settings = window.kea_language_blocks.settings;
const POST_TITLE_LENGTH = 5;

const MultipleChoice = ({postType}) =>
{

    const [ meta, setMeta ] = useEntityProp( 'postType', postType, 'meta' ); 
    const initialValues = {}; 
    initialValues.set = false;
    const defaultLang = 'en';
    const additionalLangs = ['ru'];
    const supportedLangs = [defaultLang, ...additionalLangs];
    const metaFieldValue = meta[ '_kea_activity_meta' ]; 
    let globalErrors = {};
   
    let formSubmitting = false;
    const setFormSubmitting = (flag) =>
    {
        formSubmitting  = flag; 
    }

    const { postTitle } = useSelect(
        ( select ) => ( {
            postTitle: select('core/editor').getEditedPostAttribute('title')
        } ),
        []
    );
    const alertRef = useRef();


    //validate form, if ok build XML (second validation step - test for valid
    //valid XML - call setMeta to update the meta field with the XML
    const processForm = (values) =>
    {
       
        let parser = new DOMParser();
        let xml = '<?xml version="1.0" encoding="UTF-8"?><activity></activity>';
        let xmlDoc = parser.parseFromString(xml,"text/xml");

        let rootNode = xmlDoc.getElementsByTagName("activity")[0];
        rootNode.setAttribute("type", "multiplechoice");
        //rootNode.setAttribute("ageGroup", values.ageGroup);
        //rootNode.setAttribute("level", values.level);

        let legacyNameNode = xmlDoc.createElement("legacyName");
        let legacyNameValueNode = xmlDoc.createTextNode(values.legacyName);
        legacyNameNode.appendChild(legacyNameValueNode);
        xmlDoc.getElementsByTagName("activity")[0].appendChild(legacyNameNode);

        let titleNode = xmlDoc.createElement("title");
        let titleValueNode = xmlDoc.createTextNode(postTitle);
        titleNode.appendChild(titleValueNode);
        xmlDoc.getElementsByTagName("activity")[0].appendChild(titleNode);

        let modelsNode = xmlDoc.createElement("models");
        let modelsValueNode = xmlDoc.createTextNode(values.models);
        modelsNode.appendChild(modelsValueNode);
        xmlDoc.getElementsByTagName("activity")[0].appendChild(modelsNode);

        let explanationNode = xmlDoc.createElement("explanation");
        let explanationValueNode = xmlDoc.createTextNode(values.explanation);
        explanationNode.appendChild(explanationValueNode);
        xmlDoc.getElementsByTagName("activity")[0].appendChild(explanationNode);

        let instructionsNode = xmlDoc.createElement("instructions");
        values.instructions.forEach(function(item, i)
        {
            let iNode = xmlDoc.createElement("instruction");
            iNode.setAttribute("lang", item.lang);
            let iValueNode = xmlDoc.createTextNode(item.text);
            iNode.appendChild(iValueNode);
            instructionsNode.appendChild(iNode);
        });
        xmlDoc.getElementsByTagName("activity")[0].appendChild(instructionsNode);

        let questionsNode = xmlDoc.createElement("questions");
        values.questions.forEach(function(item, i)
        {
            let qNode = xmlDoc.createElement("q"+i);
            qNode.setAttribute("questionNumber", (i + 1));

            let questionNode = xmlDoc.createElement("question");
            let questionNodeValue = xmlDoc.createTextNode(item.question);
            questionNode.appendChild(questionNodeValue);
            qNode.appendChild(questionNode);

            let answersNode = xmlDoc.createElement("answers");


            item.answers.forEach((answer, i2) => {
                let answerNode = xmlDoc.createElement("answer");
                if (i2 == 0)
                {
                    answerNode.setAttribute("variant", "correct");
                }
                else
                {
                    answerNode.setAttribute("variant", "incorrect");
                }
                let answerNodeValue = xmlDoc.createTextNode(answer);
                answerNode.appendChild(answerNodeValue);
                answersNode.appendChild(answerNode); 
            });

            qNode.appendChild(answersNode);
            questionsNode.appendChild(qNode);
            
        });
        xmlDoc.getElementsByTagName("activity")[0].appendChild(questionsNode );

        let s = new XMLSerializer();
        let newXmlStr = s.serializeToString(xmlDoc);
        values.rawXML = newXmlStr;
       
    

        //https://developer.wordpress.org/block-editor/how-to-guides/metabox/meta-block-3-add/ 
        //this seems to cause a re-render of the component. does it?
        //but does not save anything to the backend - that takes saving the whole post
        //via the button on the page?
        //console.log("setMeta");
        console.log(" meta1", meta);
        setMeta( { ...meta, _kea_activity_meta: newXmlStr, _kea_activity_type: "multiplechoice" } );
        
        
    }

    /*  TODO
        Ideally I would like to pick up taxonomies dynamically so I did not have to rebuild the f/e but this seems hard.
        https://stackoverflow.com/questions/76573295/wordpress-gutenberg-or-react-useselect-for-dynamic-data

         const availableTaxonomies = useSelect(
        ( select ) => wp.data.select('core').getEntitiesConfig('taxonomy', {per_page: 100}),
            []
        );

        then do everything dynamically but....
        for now we have to hardcode the taxonomies for labels. 
    */

    const grammarTaxonomy =  useSelect(
        ( select ) => wp.data.select('core').getEntityRecords('taxonomy', "grammar", {per_page: 100, context: "view", call: 'kea'}) 

    ); 

   const russianGrammarTaxonomy =  useSelect(
    ( select ) => wp.data.select('core').getEntityRecords('taxonomy', "russian_grammar", {per_page: 100, context: "view", call: 'kea'})
                 
    );

    const terms = [];
    let userLabels = [];
 

    if (grammarTaxonomy != undefined) {
        grammarTaxonomy.forEach((item => {
            terms[item.id] = item.name;
        }));
    }

    if (russianGrammarTaxonomy != undefined) {
        russianGrammarTaxonomy.forEach((item => {
            terms[item.id] = item.name;
        }))
    }

    //detect user changing taxonomy terms
    //i think this weill get the latest unsaved values    
    const [grammarTerms, setGammarTerms] = useEntityProp( 'postType', postType, 'grammar_terms' ); 
    const [russianGrammarTerms, setRussianGrammarTerms] = useEntityProp( 'postType', postType, 'russian_grammar_terms' ); 
  


    if (grammarTerms != undefined)
    {
        grammarTerms.forEach((item) => {
            userLabels.push(terms[item]);
        });
    }
    
    if (russianGrammarTerms != undefined)
    {
        russianGrammarTerms.forEach((item) => {
            userLabels.push(terms[item]);
        });
    }
    

 
 
    
    const blockProps = useBlockProps();//? gets props passed to this 'edit' component?

    const ErrorMessage = ({postTitle}) =>
    {
        const { isValid } = useFormikContext();
      
        if (isValid && (postTitle.length >= POST_TITLE_LENGTH))
        {   
            return <></>
        }
        else
        {
            return <Alert variant="danger">{LABELS[settings.defaultUserLang]['please_check_the_form']['nominative']}</Alert>;
        }
    };
    
    function setInitialValues() {
            
            
            //The hook useEntityProp can be used by the blocks to get or change meta values.
            //in the register or to the backend? 
            //https://developer.wordpress.org/block-editor/how-to-guides/metabox/meta-block-3-add/ 
            
            
            //TODO get this from backend or make it configureable
            //also make en default
    
            //is this a mistake? newValue is the event?
            //this does the rest call to save the data?
            
            //https://github.com/jaredpalmer/formik/issues/445#issuecomment-366952762
            //this is case of new activity?
            if ( (metaFieldValue == "")   ||  (metaFieldValue == undefined) )
            {
            
                initialValues.type = postType;
                //initialValues.ageGroup = 0;
                //initialValues.level = 0;
                initialValues.legacyName = '';
                initialValues.models = '';
                initialValues.explanation = '';
                //initialValues.questions = [{question: '', answers: ['', '','','']}];
                //initialValues.questions = [{question: '', answers: []}];
                initialValues.questions = [];
                initialValues.instructions = [];
                for (const lang of supportedLangs)
                {
                    initialValues.instructions.push({lang: lang, text: ''});
                }
              
                
            }
            else //set initialValues for form based on XML string loaded from postmeta
            {
                
                let parser = new DOMParser();
                let xmlDoc = parser.parseFromString(metaFieldValue, "text/xml");
                let rootNode = xmlDoc.getElementsByTagName("activity")[0];
                initialValues.type = rootNode.getAttribute("type");
                //initialValues.ageGroup = rootNode.getAttribute("ageGroup");
                //initialValues.level = rootNode.getAttribute("level");

                let legacyNameNodes = xmlDoc.getElementsByTagName("legacyName");
                
                if ((legacyNameNodes.length > 0) && (legacyNameNodes[0].childNodes.length > 0) )
                {
                    initialValues.legacyName = legacyNameNodes[0].childNodes[0].nodeValue; 
                }
                else
                {
                    initialValues.legacyName = '';  
                }

    

                let modelsNodes = xmlDoc.getElementsByTagName("models");
                if ((modelsNodes.length > 0) && (modelsNodes[0].childNodes.length > 0))
                {
                    initialValues.models = modelsNodes[0].childNodes[0].nodeValue;     
                }
                else
                {
                    initialValues.models = '';
                }

                let explanationNodes = xmlDoc.getElementsByTagName("explanation");
                if ((explanationNodes.length > 0) && (explanationNodes[0].childNodes.length > 0))
                {
                    initialValues.explanation = explanationNodes[0].childNodes[0].nodeValue;     
                }
                else
                {
                    initialValues.explanation = '';
                }



                let questionNodes = xmlDoc.getElementsByTagName("questions");
                
                
                if (questionNodes.length > 0)
                {
                    let theQuestionsNode = questionNodes[0];
                    initialValues.questions = [];

                    for (let el of theQuestionsNode.childNodes) { 

                        //let questionNumber = el.getAttribute("questionNumber");
                        let questionNode = el.getElementsByTagName("question")[0];
                        let questionText = questionNode.textContent;
                        let questionAndAnswers = {question: questionText, answers: []};

                        let answerNodes = el.getElementsByTagName("answers");
                        //TODO - we are assuming our array order has been preserved
                        answerNodes[0].childNodes.forEach((answerNode) => {
                            questionAndAnswers.answers.push(answerNode.textContent);
                        });
                   
                        initialValues.questions.push(questionAndAnswers); 
                    }
                }
                else
                {
                    initialValues.questions = [];        
                }

                let instructionsNodes = xmlDoc.getElementsByTagName("instructions");
                let instructionsHolder = new Array();
                if (instructionsNodes != null)
                {
                    let instructionNodes = instructionsNodes[0].childNodes;
                    instructionNodes.forEach((el) => {
                        const lang = el.getAttribute("lang");
                        instructionsHolder[lang] = el.textContent;     
                    });
                }
                initialValues.instructions = new Array();
                
                for (const lang of supportedLangs)
                {
                    if (lang in instructionsHolder)
                    {   
                        initialValues.instructions.push({lang: lang, text: instructionsHolder[lang]});    
                    }
                    else
                    {
                        initialValues.instructions.push({lang: lang, text: '' });   
                    }
                }

                let s = new XMLSerializer();
                let newXmlStr = s.serializeToString(xmlDoc);
                initialValues.rawXML = newXmlStr;
                

            }
            
            initialValues.set = true;
        }
        if (!initialValues.set)
        {
            setInitialValues();
        }
        


    return <div ref={alertRef}>
        {(postTitle.length < POST_TITLE_LENGTH) ? <div className="field-error">{LABELS[settings.defaultUserLang]['minimum_length_title']['nominative']}</div> : ''}  
            
        <Formik
            initialValues={initialValues}

            validate={values => {
                
                
                if (formSubmitting == true)
                {
                    return {};
                }
                
                let errors = {};
            
                /*
                if (values.ageGroup == 0) {
                    errors.ageGroup = "Required"; 
                }
                if (values.level == 0) {
                    errors.level = "Required"; 
                }
                */
        
                //TODO could valdiate for correct format ___ and |
            
                //https://formik.org/docs/guides/arrays
                //todo how to do my array of questions https://formik.org/docs/api/fieldarray

                if (settings.site == "repititor")
                {
                    let instructionsError = true;
                    let instructionsCount = 0;
                    values.instructions.forEach((item, idx) =>
                    {
                        
                        if (item.text != '')
                        {
                            instructionsError = false;
                        }

                        instructionsCount = idx;
                    });

                    if (instructionsError) {
                        errors.instructions = new Array();
                        errors.instructions[instructionsCount] = {"lang": '', "text": "At least one language must have instructions"};
                    }
                }
                else
                {
                    values.instructions.forEach((item, idx) =>
                    {
                        if (item.lang == defaultLang)
                        {
                            if (item.text == '')
                            {
                                errors.instructions = new Array();
                                errors.instructions[idx] = {"lang": '', "text": "Required"};
                            }
                        }
                    });

                }
            
                
            
                //let errorObj = {"question": '', "answers": []};
                values.questions.forEach((item, idx) =>
                {
                    
                    
                    //questions
                    if (values.questions[idx].question == '')
                    {
                        if (errors.questions == undefined)
                        {
                            errors.questions = new Array();
                        }
                        if (errors.questions[idx] == undefined)
                        {
                            errors.questions[idx] = {question: '', answers: []};
                        }
                    
                        errors.questions[idx].question = "Required";
                    }
                    

                    //answers
                    for (let i = 0;  i < 4; i++)
                        {
                            if (values.questions[idx].answers[i] == undefined || values.questions[idx].answers[i] == '' )
                            {
                                if (errors.questions == undefined)
                                {
                                    errors.questions = new Array();
                                }
                                if (errors.questions[idx] == undefined)
                                {
                                    errors.questions[idx] = {question: '', answers: []};
                                }
                                errors.questions[idx].answers[i] = "Please enter a value";
                            }
                            
                    }

                    
                })
                globalErrors = errors;
                return errors;
            }}
            
            onSubmit={(values, formikBag) => {

              
                //TODO - what is all this???? it looks like we are repeating the validation ? TODO what does 'TEST ONLY' mean? can we just get rid of this?
                setFormSubmitting(false);//can we use isSubmitting? maybe from context? TODO yes
                formikBag.validateForm();
            
                //TEST ONLY  
                let touched = {}
                if (!_.isEmpty(globalErrors) )
                {
                    if (globalErrors.title != undefined)
                    {
                        touched.title = true;
                    }
                    if (globalErrors.instructions != undefined)
                    {
                        touched.instructions = Array();
                        globalErrors.instructions.forEach((instruction, idx) => {
                            touched.instructions[idx] =  {lang: '', text: true}; 
                        });
                    }
                    if (globalErrors.questions != undefined)
                    {
                        touched.questions = [];
                        globalErrors.questions.forEach((item, idx) => {
                            touched.questions.push({question: true, answers: [true, true, true, true]});
                        });
                    }
                }

                formikBag.setTouched(touched);
                
                if (_.isEmpty(globalErrors))
                {
                    if (postTitle.length < POST_TITLE_LENGTH )
                    {
                       alertRef.current?.closest(".block-editor-writing-flow").scrollIntoView({ behavior: 'smooth' });
                    } 
                    else 
                    {
                        processForm(values);
                    }
                }
        
                
            }}
                
        >
            {({
                values,
                errors,
                touched,
                handleChange,
                setFieldValue,
                handleBlur,
                handleSubmit,
                isSubmitting,
                setFieldTouched,
                setTouched,
                validateForm,
                isValid
        
            }) => (
            
        
                
            <Form  onSubmit={(e) => {

                setFormSubmitting(true);
                handleSubmit(e)
                }
                } name="activty" id="activity" className="">

            
                <ErrorMessage postTitle={postTitle}></ErrorMessage>


                <Form.Group as={Row}>
                    <Form.Label column sm={2}>Legacy Name (optional)</Form.Label>
                    <Col md={10}>
                        <Form.Control md={10} name="legacyName" id="legacyName"
                            onChange={handleChange}
                            onBlur={handleBlur}
                            value={values.legacyName}
                            isInvalid={!!errors.legacyName && !!touched.legacyName}
                        ></Form.Control>
                        {errors.legacyName && touched.legacyName ? 
                            <div className="invalid-feedback">
                            {errors.legacyName}
                            </div> : null
                        }
                    </Col>
                </Form.Group>


                <Form.Group as={Row}> 
                        <Col>
                            <h3>Instructions</h3>
                        </Col>
                </Form.Group>        

                <div>     
                    {values.instructions.length > 0 && values.instructions.map((instruction, idx) =>           
                                <Instruction instruction={instruction} idx={idx} />
                    )}     
                </div>

                <Form.Group as={Row}> 
                        <Col>
                            <h3>Models</h3>
                        </Col>
                </Form.Group>   

                <Form.Group as={Row}>
                    <Col md={2}>
                        
                    </Col>
                    <Col md={10}>
                        
                        
                        <RichText name="models" id="models"
                            className="rich-input-control mt-3"
                            tagName="div" 
                            value={ values.models } 
                            allowedFormats={ [ 'core/bold', 'core/italic' ] } 
                            onChange={ ( content ) => {
                                setFieldValue("models", content);
                            } } 
                            
                        />

                    </Col>
                </Form.Group>

                <Form.Group as={Row}> 
                        <Col>
                            <h3>Explanation</h3>
                        </Col>
                </Form.Group>   

                <Form.Group as={Row}>
                    <Col md={2}>
                        
                    </Col>
                    <Col md={10}>
                        
                        
                        <RichText name="explanation" id="explanation"
                            className="rich-input-control mt-3"
                            tagName="div" 
                            value={ values.explanation } 
                            allowedFormats={ [ 'core/bold', 'core/italic' ] } 
                            onChange={ ( content ) => {
                                setFieldValue("explanation", content);
                            } } 
                            
                        />

                    </Col>
                </Form.Group>



                <Row>
                    <Col>
                        <h3>Questions</h3>
                        <p>Write the question in the question box. Then put 4 variants in the 4 answer boxes. The first box should contain the <span class="font-italic">correct</span> variant. 
                        The order of the answers in the drop-down presented to the user will be randomised. 
                            </p>
                    </Col>
                </Row>

                <FieldArray name="questions" validateOnChange={true}>
                {({ insert, remove, push }) => (
                    <div>     
                        {values.questions.length > 0 && values.questions.map( (item, idx) =>            
                            <MultipleChoiceQuestion idx={idx} 
                                insert={insert}
                                remove={remove}
                                touched={touched} 
                                >
                            </MultipleChoiceQuestion>)
                        }
                        <div className="text-right margin-top-10">
                            <button
                                className="secondary btn btn-primary"
                                type="button"
                                onClick={() => push({ question: '', answers: [] })}>
                            +
                            </button>
                        </div>
                    </div>
                )}
                </FieldArray>
                
                <Form.Group as={Row}>
                    <Col md={12}>
                            {/*<Button onClick={() => addQuestion() }>+</Button>*/}
                    </Col>
                </Form.Group>

                <Form.Group as={Row}>
                    <Col sm={{ span: 10, offset: 0 }}>
                        <div className="px-1 py-1 mt-3 mb-3">
                            {userLabels.map((item, i) => {
                                return <span className="badge rounded-pill bg-info text-dark me-2" key={i}>{item}</span>
                            })}
                        </div>
                    </Col>
                </Form.Group> 

                <Form.Group as={Row}>
                    <Col md={12}>
                    
                        <Form.Label>Activity XML</Form.Label>
                        <Form.Control as="textarea" id="rawXML" name="rawXML" rows={6}
                            readOnly value={ values.rawXML }></Form.Control>
                    
                    </Col>
                </Form.Group>
            

                
                <Form.Group as={Row}>
                    <Col sm={{ span: 10, offset: 0 }}>
                        <button id="activityButton" role="link" type="submit" >
                            Update
                        </button>
                    </Col>
                </Form.Group> 

        

                
            </Form>
            )}
        </Formik>
   

</div>
    
}


export {

    MultipleChoice
}
