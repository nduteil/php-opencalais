<?php

include('vendor/autoload.php');

// source : https://www.reuters.com/article/us-france-usa/as-macron-heads-to-u-s-strong-relationship-with-trump-under-test-idUSKBN1HQ0SE
$document = array(
    'title' => 'As Macron heads to U.S., \'strong relationship\' with Trump under test',
    'abstract' => 'PARIS (Reuters) - When France’s ambassador to Washington told American officials last July that he was heading to Paris and would shortly see President Emmanuel Macron, one of them handed him a copy of the New York Times.',
);
$document['body'] = <<<EOT
In it, he read the words “Yes, Emmanuel. It’s true, I love You” written in highlighter next to an article about the French leader’s good relationship with U.S. President Donald Trump.
Whether Trump scribbled the words himself is unclear, but coming just two weeks after he had been hosted in great pomp at the Bastille Day military parade in Paris, it showed just how strong Franco-American ties were.
As he arrives in Washington on Monday for a three-day state visit, that good rapport will be tested as Macron tries to sway Trump on key issues from Syria to Iran and trade after a year spent investing a lot of political capital with few returns.
While he has delivered on promises of change at home and is pushing his views in Europe, the world stage is proving tougher terrain. Like others before him, Macron has found predicting Trump a challenge.
With the exception of one unusually prolonged and firm handshake, Macron has opted for a non-confrontational approach toward the unconventional U.S. president, hoping that by engaging with him he could win concessions.
He played to Trump’s admiration for the military and grandeur by inviting him to Paris for the annual July 14 celebrations and dining him at the Eiffel Tower. The soft diplomacy aimed to gain Trump’s confidence and influence U.S. foreign policy at a time European diplomats say Washington lacks direction.
Macron has spoken to Trump by phone in the last year more than with any other leader, including German Chancellor Angela Merkel, arguably becoming Trump’s bridge to Europe.
“He is not a classic politician,” Macron said in a January interview just days after Trump tweeted about nuclear war with North Korea and gave an ultimatum to “fix” the Iran nuclear deal.
“We’ve built a strong relationship. We disagree on several topics. I’m always extremely direct and frank and he is too. Sometimes I manage to convince him, sometimes I fail.”
The relationship has suited Trump.
He needed a friend overseas. His preference for a more unilateral, transactional diplomacy had unsettled traditional allies in Europe and left him appearing isolated among world leaders.
Besides, diplomats say that France’s military role fighting Islamist militants in West Africa and Syria has opened doors in Washington.

TESTS ON TRADE, IRAN

The next two weeks will provide a critical test of what influence, if any, the French president can have on his American counterpart.
Trump has given the European Union until May 1 to negotiate permanent exemptions from steel and aluminum tariffs and France, Britain and Germany until May 12 to “fix” the Iran nuclear deal with world powers.
“Macron has been trying to build alliances and wants to be the bridge between the U.S. and Russia,” said a senior former U.N. official. “There comes a point where that kind of political messaging has to be backed up with results.”
Macron’s desire to keep the 2015 Iran nuclear deal, while offering to be tough on Tehran’s ballistic missile program and regional activities has yet to assuage Trump.
“We’re hoping that Macron will find the arguments to convince Trump to not commit this mistake, but we’re not very optimistic,” said a French diplomatic source.

MERKEL COORDINATION

Macron’s visit will be followed on April 27 by Merkel, whose relationship with Trump has been markedly more tense.
Before a phone conversation on March 1 to discuss the war in Syria and Russian nuclear arms, the two leaders had not spoken to each other for more than five months.
Rather than being subjected to a public dressing down, like Merkel over Germany’s trade policy for example, Macron has been spared criticism.
The French and German leaders meet in Berlin on Thursday to ensure they are on same page on Iran and trade ahead of their trips, a presidential source said.
Macron’s good relationship with Trump has had benefits. U.S. companies overtook German ones as the top corporate investors in the French economy last year, with U.S. investments up 26 percent.
But he appears to have limited the damage rather than be able to claim resounding successes.
Although he failed to convince Trump to stay in the Paris climate deal, the U.S. president did not oppose Macron pushing for American firms and states to act on climate independently.
Macron has been worried about Trump feeling backed into a corner and has sensed an opportunity to sway U.S. thinking and elevate France in global affairs, especially over Syria and the Middle East.
However, Trump’s unpredictability means that nobody knows whether a Macron factor really does exist.
After French, British and U.S. strikes on Syrian government targets last week, Macron boasted publicly that he had persuaded Trump to maintain U.S. engagement in Syria for the long-term. Within hours, he was met with a rebuke by the White House.
“We’ve seen the decisions Trump made, but we don’t know what decisions he could have taken if we hadn’t had this dialogue,” said a senior French official.
EOT;

try {
    $oc = new OpenCalais\OpenCalais('YOUR_API_KEY');

    // set input format (default is text/raw)
    $oc->setInputContentType('text/xml');

    // transform $document array to xml string
    $document = OpenCalais\XmlUtils::arrayToXml($document);

    // get entities for document
    $entities = $oc->getEntities($document);
    
    // get topics for document
    // as all parts are stored in object at the first query
    // $document is optional at this stage
    $topics = $oc->getTopics();

    // get social tags for document
    // as all parts are stored etc.
    $socialTags = $oc->getSocialTags();

    // get the API raw response for archive
    $rawResponse = $oc->getLastAPIResponse();

    // CLI style output
    if (count($topics)) {
        echo "TOPICS\n";
        foreach ($topics as $topic => $topicData) {
            echo "$topic ({$topicData['score']})\n";
        }
    }

    if (count($entities)) {
        echo "ENTITIES\n";
        foreach ($entities as $type => $entitiesByType) {
            echo "$type\n";
            foreach ($entitiesByType as $entity => $entityData) {
                echo "\t$entity ({$entityData['relevance']})\n";
            }
        }
    }

    if (count($socialTags)) {
        echo "SOCIAL TAGS\n";
        foreach ($socialTags as $socialTag => $socialTagData) {
            echo "$socialTag ({$socialTagData['importance']})\n";
        }
    }

    echo $rawResponse;

}
catch (Exception $e) {
    echo 'Error : ',  $e->getMessage(), "\n";
}
